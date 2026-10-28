<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Support\EnforcingPreventRequestForgery;
use Tests\TestCase;

/**
 * Real CSRF enforcement for stateful SPA requests (contract v0.4 §1.2, §1.4; D-019).
 *
 * Laravel 13 skips CSRF in tests, so every case except the control runs with
 * EnforcingPreventRequestForgery as Sanctum's CSRF middleware (read from config per request).
 */
class CsrfEnforcementTest extends TestCase
{
    use InteractsWithSpa;
    use RefreshDatabase;

    private const PHONE = '+94700000123';

    public function test_control_without_the_override_laravel_skips_csrf_in_tests(): void
    {
        // Proves the enforcement tests below are meaningful: by default this request passes.
        $this->requestOtp(self::PHONE)->assertCreated();
    }

    public function test_a_stateful_post_without_a_token_is_rejected_with_419(): void
    {
        $this->enforceCsrf();

        $this->requestOtp(self::PHONE)
            ->assertStatus(419)
            ->assertJsonPath('message', 'CSRF token mismatch.')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_a_stateful_post_with_a_wrong_token_is_rejected_with_419(): void
    {
        $this->enforceCsrf();
        $this->spa('GET', '/sanctum/csrf-cookie')->assertNoContent();

        $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'login'], [
            'X-XSRF-TOKEN' => 'not-the-token',
        ])->assertStatus(419);

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_a_same_site_fetch_without_a_token_is_still_rejected(): void
    {
        // localhost:5173 → localhost:8000 is "same-site", not "same-origin": the token is required.
        $this->enforceCsrf();
        $this->spa('GET', '/sanctum/csrf-cookie')->assertNoContent();

        $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'login'], [
            'Sec-Fetch-Site' => 'same-site',
        ])->assertStatus(419);
    }

    public function test_a_stateful_post_with_the_xsrf_token_from_the_csrf_cookie_passes(): void
    {
        $this->enforceCsrf();

        $cookieResponse = $this->spa('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $xsrf = $cookieResponse->getCookie('XSRF-TOKEN', decrypt: false);
        $this->assertNotNull($xsrf, 'GET /sanctum/csrf-cookie must set the XSRF-TOKEN cookie.');

        $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'login'], [
            'X-XSRF-TOKEN' => $xsrf->getValue(),
        ])->assertCreated();

        $this->assertDatabaseCount('otp_challenges', 1);
    }

    public function test_the_full_login_flow_passes_with_tokens_and_the_session_endpoint_then_works(): void
    {
        $this->enforceCsrf();
        $xsrf = fn () => ['X-XSRF-TOKEN' => $this->spa('GET', '/sanctum/csrf-cookie')->getCookie('XSRF-TOKEN', decrypt: false)->getValue()];

        $challengeId = $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => self::PHONE, 'purpose' => 'login'], $xsrf())
            ->assertCreated()->json('data.challenge_id');

        $this->spa('POST', '/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId, 'phone' => self::PHONE, 'code' => $this->sentCode(self::PHONE),
        ], $xsrf())->assertOk();

        $this->spa('GET', '/api/v1/auth/session')->assertOk()->assertJsonPath('data.user.phone', self::PHONE);
    }

    public function test_a_non_stateful_request_cannot_use_session_endpoints_to_bypass_csrf(): void
    {
        // Sanctum skips session and CSRF middleware for non-stateful requests, so verify refuses them.
        $this->enforceCsrf();

        $this->nonStateful('POST', '/api/v1/auth/otp/verify', [
            'challenge_id' => '00000000-0000-4000-8000-000000000000', 'phone' => self::PHONE, 'code' => '123456',
        ])->assertForbidden();
    }

    private function enforceCsrf(): void
    {
        config(['sanctum.middleware.validate_csrf_token' => EnforcingPreventRequestForgery::class]);
    }
}
