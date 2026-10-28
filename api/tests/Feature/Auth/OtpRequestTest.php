<?php

namespace Tests\Feature\Auth;

use App\Models\OtpChallenge;
use App\Otp\OtpCodeHasher;
use App\Otp\OtpDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/auth/otp/request (contract v0.4 §4.1; D-019).
 */
class OtpRequestTest extends TestCase
{
    use AppFixtures;
    use InteractsWithSpa;
    use RefreshDatabase;

    private const PHONE = '+94700000201';

    public function test_it_creates_a_challenge_and_never_returns_the_code(): void
    {
        $response = $this->requestOtp(self::PHONE)->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['data' => ['challenge_id', 'expires_at', 'resend_available_at'], 'meta' => ['otp_driver', 'dev_mode']])
            ->assertJsonPath('meta.otp_driver', 'fake')
            ->assertJsonPath('meta.dev_mode', false)
            ->assertJsonMissingPath('data.code');

        $code = $this->sentCode(self::PHONE);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertStringNotContainsString($code, $response->getContent());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('data.expires_at'));
    }

    public function test_the_code_is_stored_only_as_an_hmac_bound_to_the_challenge(): void
    {
        $challengeId = $this->requestOtp(self::PHONE)->json('data.challenge_id');
        $challenge = OtpChallenge::query()->where('uuid', $challengeId)->sole();
        $code = $this->sentCode(self::PHONE);
        $hasher = app(OtpCodeHasher::class);

        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertSame($hasher->codeHash($challengeId, self::PHONE, $code), $challenge->code_hash);
        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertSame(64, strlen($challenge->request_ip_hash));
        $this->assertSame(0, $challenge->attempts);
        $this->assertSame(5, $challenge->max_attempts);
    }

    public function test_known_and_unknown_phones_get_identical_responses_without_reading_users(): void
    {
        $known = $this->makeUser()->phone;
        $unknown = '+94700000999';

        DB::enableQueryLog();
        $knownResponse = $this->requestOtp($known)->assertCreated();
        $unknownResponse = $this->requestOtp($unknown)->assertCreated();

        $this->assertSame($knownResponse->status(), $unknownResponse->status());
        $this->assertSame(array_keys($knownResponse->json('data')), array_keys($unknownResponse->json('data')));
        $this->assertSame($knownResponse->json('meta'), $unknownResponse->json('meta'));
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('`users`', $query['query'], 'OTP requests must never read the users table.');
        }
    }

    public function test_invalid_phone_and_purpose_are_rejected_with_422(): void
    {
        $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => '0771234567', 'purpose' => 'login'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'signup'])
            ->assertUnprocessable()->assertJsonValidationErrors('purpose');
    }

    public function test_a_resend_within_the_cooldown_is_rejected_with_retry_after(): void
    {
        $this->requestOtp(self::PHONE)->assertCreated();

        $this->travel(30)->seconds();
        $this->requestOtp(self::PHONE)->assertStatus(429)->assertHeader('Retry-After', '30');

        $this->assertSame(1, OtpChallenge::query()->count());
    }

    public function test_a_resend_creates_a_new_challenge_and_expires_the_older_one(): void
    {
        $first = $this->requestOtp(self::PHONE)->json('data.challenge_id');
        $firstCode = $this->sentCode(self::PHONE);

        $this->travel(61)->seconds();
        $second = $this->requestOtp(self::PHONE)->assertCreated()->json('data.challenge_id');

        $this->assertNotSame($first, $second);
        $this->assertTrue(OtpChallenge::query()->where('uuid', $first)->sole()->expires_at->lte(now()));
        $this->verifyOtp($first, self::PHONE, $firstCode)->assertUnprocessable();
        $this->verifyOtp($second, self::PHONE, $this->sentCode(self::PHONE))->assertOk();
    }

    public function test_per_phone_limit_of_3_per_15_minutes(): void
    {
        foreach (range(1, 3) as $i) {
            $this->requestOtp(self::PHONE)->assertCreated();
            $this->travel(61)->seconds();
        }

        $this->requestOtp(self::PHONE)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(3, OtpChallenge::query()->count());
    }

    public function test_per_phone_limit_of_10_per_24_hours(): void
    {
        foreach (range(1, 10) as $i) {
            $this->requestOtp(self::PHONE)->assertCreated();
            $this->travel(16)->minutes();
        }

        $this->requestOtp(self::PHONE)->assertStatus(429);
        $this->assertSame(10, OtpChallenge::query()->count());
    }

    public function test_per_ip_limit_of_10_per_hour(): void
    {
        foreach (range(1, 10) as $i) {
            $this->requestOtp(sprintf('+9470%07d', 300 + $i))->assertCreated();
        }

        $this->requestOtp('+94700000399')->assertStatus(429);
    }

    public function test_the_log_driver_writes_the_code_only_to_the_otp_channel_and_reports_dev_mode(): void
    {
        config(['otp.driver' => 'log']);
        $this->app->forgetInstance(OtpDriver::class);
        $channel = $this->mock(LoggerInterface::class);
        $channel->shouldReceive('info')->once()->withArgs(fn (string $message) => str_contains($message, '+9470*****01')
            && preg_match('/: [0-9]{6}$/', $message) === 1);
        Log::shouldReceive('channel')->once()->with('otp')->andReturn($channel);

        $this->requestOtp(self::PHONE)->assertCreated()
            ->assertJsonPath('meta.otp_driver', 'log')
            ->assertJsonPath('meta.dev_mode', true);
    }

    public function test_the_code_never_reaches_any_other_log(): void
    {
        Log::spy();

        $this->requestOtp(self::PHONE)->assertCreated();
        $code = $this->sentCode(self::PHONE);

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldNotHaveReceived($level, fn (...$arguments) => str_contains(json_encode($arguments), $code));
        }
        Log::shouldNotHaveReceived('channel');
    }

    public function test_log_and_fake_drivers_fail_closed_in_production(): void
    {
        foreach (['log', 'fake'] as $driver) {
            config(['otp.driver' => $driver]);
            $this->app->forgetInstance(OtpDriver::class);
            $this->app['env'] = 'production';

            $this->nonStateful('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'login'])
                ->assertStatus(503);

            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, OtpChallenge::query()->count());
    }

    public function test_an_unknown_driver_fails_closed(): void
    {
        config(['otp.driver' => 'sms-that-does-not-exist']);
        $this->app->forgetInstance(OtpDriver::class);

        $this->requestOtp(self::PHONE)->assertStatus(503);
        $this->assertSame(0, OtpChallenge::query()->count());
    }

    public function test_a_missing_or_short_hmac_key_fails_closed_for_otp_only(): void
    {
        foreach ([null, '', 'base64:'.base64_encode('too-short')] as $key) {
            config(['otp.hmac_key' => $key]);

            $this->requestOtp(self::PHONE)->assertStatus(503);
            $this->verifyOtp('00000000-0000-4000-8000-000000000000', self::PHONE, '123456')->assertStatus(503);
        }

        $this->assertSame(0, OtpChallenge::query()->count());
        // Unrelated endpoints keep working.
        $this->spa('GET', '/api/v1/auth/session')->assertUnauthorized();
    }
}
