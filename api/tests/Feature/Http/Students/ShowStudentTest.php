<?php

namespace Tests\Feature\Http\Students;

use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Http\Concerns\AssertsReadOnly;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/students/{uuid} (contract v0.10 §8.6, E2b; D-028): the §8 `Student` shape
 * for the managing institute's admins and active staff (also while suspended, FD-3). super_admin
 * 403 (not a member, as §8.2); teacher-only and unrelated callers 404.
 */
class ShowStudentTest extends TestCase
{
    use AssertsReadOnly;
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401_even_for_a_malformed_uuid(): void
    {
        $this->spa('GET', $this->url($this->makeStudent($this->makeInstitute())))->assertUnauthorized();
        $this->spa('GET', '/api/v1/students/not-a-uuid')->assertUnauthorized();
    }

    public function test_admins_and_staff_get_the_same_student_shape_as_the_list(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $this->linkParent($admin, $student, $this->makeParent());

        foreach ([$admin, $this->staffOf($institute)] as $user) {
            $this->loginAs($user);
            $listed = $this->spa('GET', "/api/v1/institutes/{$institute->uuid}/students")->assertOk()->json('data.0');

            $response = $this->assertReadOnly(fn () => $this->spa('GET', $this->url($student)))
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertExactJson(['data' => $listed]);

            $this->assertSame(1, $response->json('data.active_guardian_count'));
            $this->assertSame(
                ['uuid', 'full_name', 'grade', 'exam_target', 'medium', 'date_of_birth', 'has_user_account', 'active_guardian_count', 'created_at'],
                array_keys($response->json('data')),
            );
            $this->assertNoInternalIds($response);
        }
    }

    public function test_a_suspended_institute_stays_readable_by_its_admins_and_staff(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $staff = $this->staffOf($institute);
        $this->suspend($institute);

        foreach ([$admin, $staff] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($student))->assertOk()->assertJsonPath('data.uuid', $student->uuid);
        }
    }

    public function test_super_admin_gets_403_and_everyone_else_unrelated_gets_404(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $admin = $this->adminOf($institute);
        $parent = $this->makeParent();
        $this->linkParent($admin, $student, $parent);

        $this->loginAs($this->superAdmin());
        $this->spa('GET', $this->url($student))->assertForbidden()->assertJsonMissingPath('reason');

        $formerStaff = $this->staffOf($institute);
        DB::table('institute_staff')->where('user_id', $formerStaff->getKey())->update(['status' => 'former', 'left_on' => '2026-10-15']);

        foreach ([$this->teacherAt($institute), $this->adminOf($this->makeInstitute()), $parent, $formerStaff, $this->makeUser()] as $user) {
            $this->loginAs($user);
            $this->spa('GET', $this->url($student))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }
    }

    public function test_unknown_and_malformed_students_are_404(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));

        $this->spa('GET', '/api/v1/students/'.Str::uuid())->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->spa('GET', '/api/v1/students/not-a-uuid')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_query_fields_are_rejected_only_after_authorization(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('GET', $this->url($student).'?nic=1')->assertNotFound();

        $this->loginAs($this->adminOf($institute));
        $this->spa('GET', $this->url($student).'?nic=1')->assertUnprocessable()->assertJsonValidationErrors('nic');
    }

    private function url(StudentProfile $student): string
    {
        return "/api/v1/students/{$student->uuid}";
    }
}
