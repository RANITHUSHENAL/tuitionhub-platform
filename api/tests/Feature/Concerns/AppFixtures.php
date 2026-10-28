<?php

namespace Tests\Feature\Concerns;

use App\Audit\AuditContext;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\User;
use Tests\Feature\Schema\Concerns\SchemaFixtures;

/**
 * Model-level fixtures for service, audit and authorization tests. Fictional data only; IPs come
 * from the documentation ranges (RFC 5737 / RFC 3849).
 */
trait AppFixtures
{
    use SchemaFixtures;

    protected const V4_UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    protected function makeUser(array $overrides = []): User
    {
        return User::query()->findOrFail($this->insertUser(array_merge([
            'name' => 'Fictional User',
            'phone_verified_at' => now('UTC'),
        ], $overrides)));
    }

    protected function makeInstitute(?User $owner = null, array $overrides = []): Institute
    {
        return Institute::query()->findOrFail($this->insertInstitute(array_merge([
            'owner_user_id' => ($owner ?? $this->makeUser())->getKey(),
        ], $overrides)));
    }

    protected function useAuditContext(
        ?int $actorId = null,
        ?string $ipAddress = '203.0.113.10',
        ?string $userAgent = 'TuitionHubTest/1.0',
        bool $fromRequest = true,
    ): void {
        $this->app->instance(AuditContext::class, new AuditContext($actorId, $ipAddress, $userAgent, $fromRequest));
    }

    protected function assertDomainRule(string $reason, callable $callback): void
    {
        try {
            $callback();
        } catch (DomainRuleException $exception) {
            $this->assertSame($reason, $exception->reason);

            return;
        }

        $this->fail("Expected DomainRuleException [{$reason}].");
    }
}
