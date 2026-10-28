<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * Session-only authentication (bearer tokens disabled) and the CORS policy (D-019).
 */
class BearerAndCorsTest extends TestCase
{
    use AppFixtures;
    use InteractsWithSpa;
    use RefreshDatabase;

    public function test_a_bearer_token_never_authenticates_and_no_token_table_is_queried(): void
    {
        DB::enableQueryLog();

        $this->nonStateful('GET', '/api/v1/auth/session', [], ['Authorization' => 'Bearer 1|abcdefghijklmnopqrstuvwxyz'])
            ->assertUnauthorized();
        $this->spa('GET', '/api/v1/auth/session', [], ['Authorization' => 'Bearer 1|abcdefghijklmnopqrstuvwxyz'])
            ->assertUnauthorized();

        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('personal_access_tokens', $query['query']);
        }
    }

    public function test_the_spa_origin_gets_credentialed_cors_and_other_origins_do_not(): void
    {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/v1/auth/otp/request', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ]);

        $preflight('http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // With one configured origin, fruitcake/php-cors always answers with that origin; the browser
        // rejects any other requesting origin because the value does not match it.
        foreach (['http://127.0.0.1:5173', 'http://localhost:3000', 'https://evil.example'] as $origin) {
            $allowed = $preflight($origin)->headers->get('Access-Control-Allow-Origin');
            $this->assertNotSame($origin, $allowed);
            $this->assertNotSame('*', $allowed);
        }
    }

    public function test_unknown_api_routes_get_a_plain_404_with_no_store(): void
    {
        $this->spa('GET', '/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
