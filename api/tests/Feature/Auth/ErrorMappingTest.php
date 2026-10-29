<?php

namespace Tests\Feature\Auth;

use App\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * DomainRuleException → HTTP mapping (contract v0.4 §1.3–§1.4).
 */
class ErrorMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test-only route that throws the requested rule; never registered outside this test.
        Route::middleware('api')->get('/api/v1/__test/domain-rule/{reason}', fn (string $reason) => throw new DomainRuleException($reason));
    }

    public function test_state_conflicts_are_409_with_a_reason_code(): void
    {
        foreach ([
            DomainRuleException::ROLE_ALREADY_GRANTED,
            DomainRuleException::ROLE_NOT_GRANTED,
            DomainRuleException::OWNER_ADMIN_REVOCATION_FORBIDDEN,
            DomainRuleException::ALREADY_ACTIVE,
            DomainRuleException::NOT_ACTIVE,
        ] as $reason) {
            $this->getJson("/api/v1/__test/domain-rule/{$reason}")
                ->assertStatus(409)
                ->assertJsonPath('reason', $reason)
                ->assertJsonStructure(['message', 'reason'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_not_eligible_is_403_with_a_reason_code(): void
    {
        $this->getJson('/api/v1/__test/domain-rule/'.DomainRuleException::NOT_ELIGIBLE)
            ->assertForbidden()
            ->assertJsonPath('reason', DomainRuleException::NOT_ELIGIBLE);
    }

    public function test_institute_suspended_is_403_with_a_reason_code(): void
    {
        $this->getJson('/api/v1/__test/domain-rule/'.DomainRuleException::INSTITUTE_SUSPENDED)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
    }

    public function test_invalid_values_are_422_in_laravels_errors_shape(): void
    {
        $expected = [
            DomainRuleException::UNKNOWN_DISTRICT => 'district',
            DomainRuleException::UNKNOWN_POSITION => 'position',
            DomainRuleException::INVALID_DATE => 'date',
            DomainRuleException::UNKNOWN_ROLE => 'role',
            DomainRuleException::ROLE_SCOPE_MISMATCH => 'role',
        ];

        foreach ($expected as $reason => $field) {
            $this->getJson("/api/v1/__test/domain-rule/{$reason}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field)
                ->assertJsonMissingPath('reason');
        }
    }
}
