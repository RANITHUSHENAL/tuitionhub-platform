<?php

namespace Tests\Feature\Http\Students;

use App\Models\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/institutes/{uuid}/students (contract v0.6 §8.2). Fictional students only.
 */
class ListStudentsTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLookups();
    }

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', $this->url($this->makeInstitute()))->assertUnauthorized();
    }

    public function test_unrelated_callers_get_404_and_teacher_only_and_super_admin_get_403(): void
    {
        $institute = $this->makeInstitute();
        $this->insertStudentProfile($institute->getKey());

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->url($institute))->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        foreach ([$this->teacherAt($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($institute))->assertForbidden();
        }
    }

    public function test_only_this_institutes_students_are_listed_in_name_order(): void
    {
        $institute = $this->makeInstitute();
        $other = $this->makeInstitute();
        $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional Zeta']);
        $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional Alpha']);
        $this->insertStudentProfile($other->getKey(), ['full_name' => 'Fictional Other Institute']);

        foreach ([$this->adminOf($institute), $this->staffOf($institute)] as $user) {
            $this->loginAs($user);

            $response = $this->spa('GET', $this->url($institute))->assertOk()
                ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 25, 'total' => 2, 'last_page' => 1]);
            $this->assertSame(['Fictional Alpha', 'Fictional Zeta'], array_column($response->json('data'), 'full_name'));
            $this->assertSame(
                ['uuid', 'full_name', 'grade', 'exam_target', 'medium', 'date_of_birth', 'has_user_account', 'active_guardian_count', 'created_at'],
                array_keys($response->json('data.0')),
            );
            $this->assertNoInternalIds($response);
        }
    }

    public function test_active_guardian_count_ignores_revoked_links(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional Linked']);
        $this->insertGuardianLink($student, $this->insertGuardianProfile(), ['is_primary' => 1]);
        $this->insertGuardianLink($student, $this->insertGuardianProfile());
        $this->revokeGuardianLink($this->insertGuardianLink($student, $this->insertGuardianProfile()));
        $this->loginAs($this->staffOf($institute));

        $this->spa('GET', $this->url($institute))->assertOk()->assertJsonPath('data.0.active_guardian_count', 2);
    }

    public function test_search_is_literal_and_the_grade_filter_needs_a_known_code(): void
    {
        $institute = $this->makeInstitute();
        $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional Nimali', 'grade_id' => $this->gradeId('G11')]);
        $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional Ruwan', 'grade_id' => $this->gradeId('AL2')]);
        $this->insertStudentProfile($institute->getKey(), ['full_name' => 'Fictional 100% Kumara', 'grade_id' => $this->gradeId('AL2')]);
        $this->loginAs($this->adminOf($institute));

        $names = fn (string $query) => array_column($this->spa('GET', $this->url($institute).$query)->assertOk()->json('data'), 'full_name');

        $this->assertSame(['Fictional Nimali'], $names('?q=nimali'));
        $this->assertSame(['Fictional 100% Kumara'], $names('?q='.rawurlencode('%')));
        $this->assertSame(['Fictional 100% Kumara', 'Fictional Ruwan'], $names('?grade_code=AL2'));
        $this->assertSame(['Fictional Ruwan'], $names('?grade_code=AL2&q=Ruwan'));

        $this->spa('GET', $this->url($institute).'?grade_code=G99')->assertUnprocessable()->assertJsonValidationErrors('grade_code');
        $this->spa('GET', $this->url($institute).'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->spa('GET', $this->url($institute).'?per_page=2&page=2')->assertOk()
            ->assertJsonPath('data.0.full_name', 'Fictional Ruwan')
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_a_suspended_institutes_students_stay_readable_by_admins_and_staff(): void
    {
        $institute = $this->makeInstitute();
        $this->insertStudentProfile($institute->getKey());
        $admin = $this->adminOf($institute);
        $staff = $this->staffOf($institute);
        $this->suspend($institute);

        foreach ([$admin, $staff] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($institute))->assertOk()->assertJsonPath('meta.total', 1);
        }
    }

    private function url(Institute $institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/students";
    }
}
