<?php

namespace Tests\Feature\Http\Teachers;

use App\Models\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/institutes/{uuid}/teachers (contract v0.6 §6.3; FD-3, FD-4).
 */
class InstituteTeachersTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->spa('GET', $this->url($this->makeInstitute()))->assertUnauthorized();
    }

    public function test_unrelated_callers_get_404_and_teacher_only_callers_get_403(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherAt($institute);

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->url($institute))->assertNotFound()->assertExactJson(['message' => 'Not found.']);

        $this->loginAs($teacher);
        $this->spa('GET', $this->url($institute))->assertForbidden();
    }

    public function test_admins_staff_and_super_admin_list_only_this_institutes_assignments(): void
    {
        $institute = $this->makeInstitute();
        $other = $this->makeInstitute();
        $this->teacherAt($institute, ['joined_on' => '2026-09-01']);
        $this->teacherAt($institute, ['joined_on' => '2026-10-01']);
        $this->teacherAt($other, ['joined_on' => '2026-10-15']);

        foreach ([$this->adminOf($institute), $this->staffOf($institute), $this->superAdmin()] as $user) {
            $this->loginAs($user);

            $response = $this->spa('GET', $this->url($institute))->assertOk()->assertJsonPath('meta.total', 2);
            $this->assertSame(['2026-10-01', '2026-09-01'], array_column($response->json('data'), 'joined_on'));
            $this->assertSame(['teacher', 'status', 'joined_on', 'left_on'], array_keys($response->json('data.0')));
            $this->assertSame(['uuid', 'display_name', 'headline', 'bio', 'years_experience'], array_keys($response->json('data.0.teacher')));
            $this->assertNoInternalIds($response);
        }
    }

    public function test_filters_by_status_and_paginates_deterministically(): void
    {
        $institute = $this->makeInstitute();
        $this->teacherAt($institute, ['joined_on' => '2026-10-01']);
        $this->teacherAt($institute, ['joined_on' => '2026-10-01']);
        $this->teacherAt($institute, ['status' => 'former', 'joined_on' => '2025-01-01', 'left_on' => '2025-12-31']);
        $this->loginAs($this->staffOf($institute));

        $this->spa('GET', $this->url($institute).'?status=former')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'former')
            ->assertJsonPath('data.0.left_on', '2025-12-31');
        $this->spa('GET', $this->url($institute).'?status=active')->assertOk()->assertJsonPath('meta.total', 2);

        // Same joined_on: the newer assignment comes first, and the two pages never overlap.
        $first = $this->spa('GET', $this->url($institute).'?per_page=1&page=1')->json('data.0.teacher.uuid');
        $second = $this->spa('GET', $this->url($institute).'?per_page=1&page=2')->json('data.0.teacher.uuid');
        $this->assertNotSame($first, $second);
        $this->spa('GET', $this->url($institute).'?per_page=1&page=1')->assertJsonPath('data.0.teacher.uuid', $first);

        $this->spa('GET', $this->url($institute).'?status=ended')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->spa('GET', $this->url($institute).'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_a_suspended_institutes_teachers_stay_readable_by_staff(): void
    {
        $institute = $this->makeInstitute();
        $this->teacherAt($institute);
        $staff = $this->staffOf($institute);
        $this->suspend($institute);
        $this->loginAs($staff);

        $this->spa('GET', $this->url($institute))->assertOk()->assertJsonPath('meta.total', 1);
    }

    private function url(Institute $institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/teachers";
    }
}
