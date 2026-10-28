<?php

namespace Tests\Feature\Auth\Concerns;

use App\Otp\FakeOtpDriver;
use App\Otp\OtpDriver;
use Illuminate\Testing\TestResponse;

/**
 * Drives the API the way the React SPA does: stateful Origin/Referer, JSON, credentials, and the
 * session and XSRF-TOKEN cookies carried from one response to the next (as the browser would).
 */
trait InteractsWithSpa
{
    protected const SPA_ORIGIN = 'http://localhost:5173';

    /**
     * Called automatically by Laravel's test lifecycle. Every A1 test uses a throwaway OTP key;
     * no developer or committed key is ever needed.
     */
    protected function setUpInteractsWithSpa(): void
    {
        config([
            'otp.hmac_key' => 'base64:'.base64_encode(random_bytes(32)),
            // The .env.example driver: sessions live in the (test) database, so a fresh session
            // store per request reads only what was persisted under the cookie's session ID.
            'session.driver' => 'database',
        ]);
    }

    protected function spa(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->startFreshRequest();

        // Per-request headers (not withHeaders(), which would leak Origin into later nonStateful() calls).
        $response = $this->withCredentials()->json($method, $uri, $data, array_merge([
            'Origin' => self::SPA_ORIGIN,
            'Referer' => self::SPA_ORIGIN.'/',
            'Accept' => 'application/json',
        ], $headers));

        $this->rememberSpaCookies($response);

        return $response;
    }

    /**
     * A request that is NOT from the stateful SPA (no Origin/Referer), like curl or a mobile client.
     * It still carries any remembered cookies, which must not help it.
     */
    protected function nonStateful(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->startFreshRequest();

        return $this->json($method, $uri, $data, array_merge(['Accept' => 'application/json'], $headers));
    }

    protected function requestOtp(string $phone): TestResponse
    {
        return $this->spa('POST', '/api/v1/auth/otp/request', ['phone' => $phone, 'purpose' => 'login']);
    }

    protected function verifyOtp(string $challengeId, string $phone, string $code): TestResponse
    {
        return $this->spa('POST', '/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'phone' => $phone, 'code' => $code]);
    }

    /**
     * Requests a code and logs in with it, returning the verify response.
     */
    protected function loginWithOtp(string $phone): TestResponse
    {
        $challengeId = $this->requestOtp($phone)->assertCreated()->json('data.challenge_id');

        return $this->verifyOtp($challengeId, $phone, $this->sentCode($phone));
    }

    protected function sentCode(string $phone): string
    {
        $driver = app(OtpDriver::class);
        $this->assertInstanceOf(FakeOtpDriver::class, $driver);

        return (string) $driver->lastCodeFor($phone);
    }

    protected function sessionCookieName(): string
    {
        return (string) config('session.cookie');
    }

    /**
     * The decrypted session ID carried in a response's session cookie.
     */
    protected function sessionIdFrom(TestResponse $response): ?string
    {
        return $response->getCookie($this->sessionCookieName())?->getValue();
    }

    /**
     * Laravel's test client reuses one application, so guards keep the user they resolved and the
     * session store keeps the attributes of an earlier request (Store::loadSession() merges into
     * them). A real HTTP request starts with neither: authentication must come from the cookies
     * sent with this request alone, otherwise logout and session-fixation tests pass falsely.
     */
    private function startFreshRequest(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    private function rememberSpaCookies(TestResponse $response): void
    {
        foreach ([$this->sessionCookieName(), 'XSRF-TOKEN'] as $name) {
            // Raw (still encrypted) values, exactly what the browser stores and sends back.
            $cookie = $response->getCookie($name, decrypt: false);

            if ($cookie !== null) {
                $this->withUnencryptedCookie($name, $cookie->getValue());
            }
        }
    }
}
