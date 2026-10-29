<?php

namespace Tests\Feature\Http\Institutes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/institutes (contract v0.5 §5.2): super_admin only.
 */
class ListInstitutesTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', '/api/v1/institutes')->assertUnauthorized();
    }

    public function test_institute_admins_staff_and_plain_users_get_403(): void
    {
        $institute = $this->makeInstitute();

        foreach ([$this->adminOf($institute), $this->staffOf($institute), $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', '/api/v1/institutes')->assertForbidden();
            // Authorization runs before validation: an invalid filter is still a 403.
            $this->spa('GET', '/api/v1/institutes?per_page=1000')->assertForbidden();
        }
    }

    public function test_super_admin_gets_a_paginated_list_ordered_by_name_with_exact_meta(): void
    {
        $this->makeInstitute(null, ['name' => 'Fictional Beta Academy']);
        $this->makeInstitute(null, ['name' => 'Fictional Alpha Academy', 'status' => 'suspended']);
        $this->makeInstitute(null, ['name' => 'Fictional Gamma Academy']);
        $this->loginAs($this->superAdmin());

        $response = $this->spa('GET', '/api/v1/institutes?per_page=2')->assertOk();

        // Exactly data and meta; no links, and exactly these meta keys (contract §1.3).
        $this->assertSame(['data', 'meta'], array_keys($response->json()));
        $this->assertSame(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $response->json('meta'));
        $this->assertSame(['Fictional Alpha Academy', 'Fictional Beta Academy'], array_column($response->json('data'), 'name'));
        $this->assertNoInternalIds($response);

        $this->spa('GET', '/api/v1/institutes?per_page=2&page=2')->assertOk()
            ->assertJsonPath('data.0.name', 'Fictional Gamma Academy');
        $this->spa('GET', '/api/v1/institutes?per_page=2&page=9')->assertOk()
            ->assertJsonPath('data', [])->assertJsonPath('meta.total', 3);
    }

    public function test_filters_by_status_district_and_literal_search_text(): void
    {
        $this->makeInstitute(null, ['name' => 'Fictional Kandy Academy', 'district' => 'Kandy']);
        $this->makeInstitute(null, ['name' => 'Fictional 100% Results', 'district' => 'Colombo', 'status' => 'suspended']);
        $this->makeInstitute(null, ['name' => 'Fictional Colombo Academy', 'district' => 'Colombo']);
        $this->loginAs($this->superAdmin());

        $names = fn (string $query) => array_column($this->spa('GET', '/api/v1/institutes'.$query)->assertOk()->json('data'), 'name');

        $this->assertSame(['Fictional 100% Results'], $names('?status=suspended'));
        $this->assertSame(['Fictional Kandy Academy'], $names('?district=kandy'));
        // % is matched literally, not as a wildcard.
        $this->assertSame(['Fictional 100% Results'], $names('?q='.rawurlencode('%')));
        $this->assertSame(['Fictional Colombo Academy'], $names('?q=Colombo&status=active'));
    }

    public function test_invalid_filters_and_page_sizes_are_rejected_with_422(): void
    {
        $this->loginAs($this->superAdmin());

        $this->spa('GET', '/api/v1/institutes?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->spa('GET', '/api/v1/institutes?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->spa('GET', '/api/v1/institutes?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->spa('GET', '/api/v1/institutes?status=closed')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->spa('GET', '/api/v1/institutes?district=Atlantis')->assertUnprocessable()->assertJsonValidationErrors('district');

        $this->spa('GET', '/api/v1/institutes?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->spa('GET', '/api/v1/institutes')->assertOk()->assertJsonPath('meta.per_page', 25);
    }
}
