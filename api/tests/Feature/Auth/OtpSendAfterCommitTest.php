<?php

namespace Tests\Feature\Auth;

use App\Models\OtpChallenge;
use App\Otp\OtpDriver;
use App\Otp\OtpService;
use App\Support\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Support\FakeDatabaseErrors;
use Tests\Support\RecordingOtpDriver;
use Tests\TestCase;
use Throwable;

/**
 * OTP request after the H1 hardening (C-2, D-022): send after commit, a failed send retires the
 * challenge, and no range update or range lock on otp_challenges.
 */
class OtpSendAfterCommitTest extends TestCase
{
    use InteractsWithSpa;
    use RefreshDatabase;

    private const PHONE = '+94700000911';

    private RecordingOtpDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new RecordingOtpDriver;
        $this->app->instance(OtpDriver::class, $this->driver);
    }

    public function test_the_code_is_sent_after_the_issuing_transaction_and_is_the_one_that_works(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->assertCreated()->json('data.challenge_id');

        $this->assertCount(1, $this->driver->sends);
        // 1 = only the test's own wrapping transaction is open: the issuing transaction has ended.
        $this->assertSame(1, $this->driver->sends[0]['transaction_level']);
        $this->assertSame(1, $this->driver->sends[0]['challenges']);

        $this->verifyOtp($challengeId, self::PHONE, $this->driver->sends[0]['code'])->assertOk();
    }

    public function test_a_failed_send_retires_the_challenge_so_its_code_can_never_be_used(): void
    {
        $outage = new RuntimeException('Fictional SMS outage.');
        $this->driver->failWith = fn () => $outage;

        try {
            app(OtpService::class)->request(self::PHONE, OtpService::PURPOSE_LOGIN, '203.0.113.10');
            $this->fail('Expected the send failure to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame($outage, $exception);
        }

        $challenge = OtpChallenge::query()->where('phone', self::PHONE)->sole();
        $this->assertTrue($challenge->expires_at->lte(now()));

        $this->driver->failWith = null;
        $this->verifyOtp($challenge->uuid, self::PHONE, $this->driver->sends[0]['code'])->assertUnprocessable();
    }

    public function test_when_retiring_fails_the_original_send_error_still_propagates_and_only_the_uuid_is_logged(): void
    {
        $outage = new RuntimeException('Fictional SMS outage.');
        $this->driver->failWith = function () use ($outage) {
            // Only now, after the challenge has committed: make the retiring UPDATE fail.
            DB::connection()->beforeExecuting(function (string $sql) {
                if (str_starts_with($sql, 'update `otp_challenges`')) {
                    throw FakeDatabaseErrors::lockWaitTimeout();
                }
            });

            return $outage;
        };

        $logged = [];
        Log::shouldReceive('warning')->once()->withArgs(function (...$args) use (&$logged) {
            $logged = $args;

            return true;
        });

        try {
            app(OtpService::class)->request(self::PHONE, OtpService::PURPOSE_LOGIN, '203.0.113.10');
            $this->fail('Expected the send failure to propagate.');
        } catch (Throwable $exception) {
            $this->assertSame($outage, $exception, 'The delivery error, not the retiring error, must propagate.');
        }

        $challenge = OtpChallenge::query()->where('phone', self::PHONE)->sole();
        $this->assertSame(['otp.retire_failed', ['challenge' => $challenge->uuid]], $logged);
        $this->assertStringNotContainsString(self::PHONE, json_encode($logged));
        $this->assertStringNotContainsString($this->driver->sends[0]['code'], json_encode($logged));

        // The documented residual: the unsent code stays valid until it expires.
        $this->assertTrue($challenge->expires_at->gt(now()));
    }

    public function test_older_open_challenges_are_expired_by_primary_key_without_range_locks(): void
    {
        $first = $this->requestOtp(self::PHONE)->assertCreated()->json('data.challenge_id');
        $this->travel(61)->seconds();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->requestOtp(self::PHONE)->assertCreated();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $challengeQueries = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, '`otp_challenges`')));
        $updates = array_values(array_filter($challengeQueries, fn (string $sql) => str_starts_with($sql, 'update')));

        $this->assertNotEmpty($updates);
        foreach ($updates as $sql) {
            $this->assertMatchesRegularExpression('/where (`otp_challenges`\.)?`id` (in \(|= \?)/', $sql, 'Updates must target primary keys.');
            $this->assertStringNotContainsString('`phone`', $sql);
        }
        foreach ($challengeQueries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/for update|lock in share mode|for share/', $sql);
        }

        $this->assertTrue(OtpChallenge::query()->where('uuid', $first)->sole()->expires_at->lte(now()));
    }

    public function test_the_per_phone_lock_outlasts_every_retried_attempt(): void
    {
        $lockWait = (int) config('database.connections.mysql.lock_wait_timeout');

        $this->assertSame(10, $lockWait);
        $this->assertGreaterThan(Transactions::MAX_ATTEMPTS * $lockWait, OtpService::requestLockSeconds());
    }
}
