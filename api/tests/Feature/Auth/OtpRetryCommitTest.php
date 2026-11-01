<?php

namespace Tests\Feature\Auth;

use App\Exceptions\ServiceBusyException;
use App\Models\OtpChallenge;
use App\Otp\OtpCodeHasher;
use App\Otp\OtpDriver;
use App\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlockingOtpCodeHasher;
use Tests\Support\RecordingOtpDriver;
use Tests\TestCase;

/**
 * C-2 / D-022: the OTP challenge is committed before its code is sent, and a code from a
 * rolled-back attempt is never sent. These tests need real commits and rollbacks at the outermost
 * level, so the database is migrated as usual but no test transaction wraps them; the rows they
 * commit to the test database are deleted afterwards.
 */
class OtpRetryCommitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+94700000901';

    private string $key;

    private RecordingOtpDriver $driver;

    /**
     * No wrapping transaction: only an outermost transaction is retried.
     *
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = 'base64:'.base64_encode(random_bytes(32));
        config(['otp.hmac_key' => $this->key]);
        $this->driver = new RecordingOtpDriver;
        $this->app->instance(OtpDriver::class, $this->driver);
    }

    protected function tearDown(): void
    {
        DB::table('otp_challenges')->where('phone', self::PHONE)->delete();

        parent::tearDown();
    }

    public function test_a_deadlocked_attempt_is_retried_and_only_the_committed_code_is_sent(): void
    {
        $hasher = new DeadlockingOtpCodeHasher($this->key, failures: 1);
        $this->app->instance(OtpCodeHasher::class, $hasher);

        $challenge = app(OtpService::class)->request(self::PHONE, OtpService::PURPOSE_LOGIN, '203.0.113.10');

        $this->assertSame(2, $hasher->calls);
        // The first attempt's row was rolled back: exactly one challenge exists, and it is committed.
        $this->assertSame(1, OtpChallenge::query()->where('phone', self::PHONE)->count());
        $this->assertCount(1, $this->driver->sends);

        $send = $this->driver->sends[0];
        $this->assertSame(0, $send['transaction_level'], 'The code must be sent outside any transaction.');
        $this->assertSame(1, $send['challenges'], 'The challenge must be committed before the send.');

        // The code that was sent is the one stored for the committed challenge.
        $stored = OtpChallenge::query()->whereKey($challenge->getKey())->sole();
        $this->assertTrue((new OtpCodeHasher($this->key))->matches($stored->code_hash, $stored->uuid, self::PHONE, $send['code']));
    }

    public function test_when_every_attempt_deadlocks_nothing_is_committed_and_no_code_is_sent(): void
    {
        $this->app->instance(OtpCodeHasher::class, new DeadlockingOtpCodeHasher($this->key, failures: 99));

        try {
            app(OtpService::class)->request(self::PHONE, OtpService::PURPOSE_LOGIN, '203.0.113.10');
            $this->fail('Expected ServiceBusyException.');
        } catch (ServiceBusyException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->driver->sends);
        $this->assertSame(0, OtpChallenge::query()->where('phone', self::PHONE)->count());
    }
}
