<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * General API rate limits (D-020): api-authenticated (60/min per user) on the protected E1a group,
 * api-public (60/min per IP) on GET /lookups. The OTP routes keep only their own limiters.
 */
class RateLimitTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_authenticated_routes_allow_60_requests_per_minute_per_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->loginAs($user);

        for ($i = 1; $i <= 60; $i++) {
            $this->spa('GET', '/api/v1/me/institutes')->assertOk();
        }

        // Every route in the group shares the same per-user budget.
        $this->spa('PATCH', '/api/v1/me', ['name' => 'Kasun Silva'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->spa('GET', '/api/v1/me/institutes')->assertStatus(429);
        $this->assertSame('Fictional User', $user->fresh()->name);

        // Keyed by user, not IP: another user on the same IP is unaffected.
        $this->loginAs($other);
        $this->spa('GET', '/api/v1/me/institutes')->assertOk();

        // The window resets after a minute.
        $this->travel(61)->seconds();
        $this->loginAs($user);
        $this->spa('GET', '/api/v1/me/institutes')->assertOk();
    }

    public function test_the_public_lookups_route_allows_60_requests_per_minute_per_ip(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $this->nonStateful('GET', '/api/v1/lookups')->assertOk();
        }

        $this->nonStateful('GET', '/api/v1/lookups')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_otp_routes_are_not_counted_against_the_general_limits(): void
    {
        // 60 lookups use up the public budget; an OTP request still succeeds under its own limiter.
        for ($i = 1; $i <= 60; $i++) {
            $this->nonStateful('GET', '/api/v1/lookups')->assertOk();
        }

        $this->requestOtp('+94700000801')->assertCreated();
        $this->nonStateful('GET', '/api/v1/lookups')->assertStatus(429);
    }
}
