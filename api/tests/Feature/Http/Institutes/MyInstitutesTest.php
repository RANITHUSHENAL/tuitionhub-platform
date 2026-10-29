<?php

namespace Tests\Feature\Http\Institutes;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/me/institutes (contract v0.5 §5.3): stored institute roles merged with active
 * teaching assignments; super_admin is not listed.
 */
class MyInstitutesTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', '/api/v1/me/institutes')->assertUnauthorized();
    }

    public function test_a_super_admin_without_institute_roles_sees_an_empty_list(): void
    {
        $this->makeInstitute();
        $this->loginAs($this->superAdmin());

        $this->spa('GET', '/api/v1/me/institutes')->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1]]);
    }

    public function test_roles_and_active_teaching_are_merged_into_my_access(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeInstitute(null, ['name' => 'Fictional A Admin Academy']);
        $staff = $this->makeInstitute(null, ['name' => 'Fictional B Staff Academy']);
        $teaching = $this->makeInstitute(null, ['name' => 'Fictional C Teaching Academy']);
        $former = $this->makeInstitute(null, ['name' => 'Fictional D Former Academy']);
        $both = $this->makeInstitute(null, ['name' => 'Fictional E Both Academy', 'status' => 'suspended']);
        $otherUsers = $this->makeInstitute(null, ['name' => 'Fictional F Other Academy']);
        $this->adminOf($otherUsers);

        $this->insertUserRole($user->getKey(), Role::SUPER_ADMIN, null);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $admin->getKey());
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_STAFF, $staff->getKey());
        $this->insertStaff($staff->getKey(), $user->getKey());
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $both->getKey());
        $teacherId = $this->insertTeacherProfile(['user_id' => $user->getKey()]);
        $this->insertAssignment($teacherId, $teaching->getKey());
        $this->insertAssignment($teacherId, $former->getKey(), ['status' => 'former', 'left_on' => '2026-10-10']);
        $this->insertAssignment($teacherId, $both->getKey());

        $this->loginAs($user);
        $response = $this->spa('GET', '/api/v1/me/institutes')->assertOk()->assertJsonPath('meta.total', 4);

        $this->assertSame([
            ['Fictional A Admin Academy', ['institute_admin']],
            ['Fictional B Staff Academy', ['institute_staff']],
            ['Fictional C Teaching Academy', ['teacher']],
            ['Fictional E Both Academy', ['institute_admin', 'teacher']],
        ], array_map(fn (array $row) => [$row['institute']['name'], $row['my_access']], $response->json('data')));

        $this->assertSame('suspended', $response->json('data.3.institute.status'));
        $this->assertSame(['institute', 'my_access'], array_keys($response->json('data.0')));
        $this->assertNoInternalIds($response);
    }

    public function test_pagination_applies_to_the_merged_list(): void
    {
        $user = $this->makeUser();

        foreach (['Fictional A Academy', 'Fictional B Academy', 'Fictional C Academy'] as $name) {
            $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $this->makeInstitute(null, ['name' => $name])->getKey());
        }

        $this->loginAs($user);

        $this->spa('GET', '/api/v1/me/institutes?per_page=2&page=2')->assertOk()
            ->assertJsonPath('data.0.institute.name', 'Fictional C Academy')
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
        $this->spa('GET', '/api/v1/me/institutes?per_page=101')->assertUnprocessable();
    }
}
