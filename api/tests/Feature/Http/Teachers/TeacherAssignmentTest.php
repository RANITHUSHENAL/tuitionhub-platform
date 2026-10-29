<?php

namespace Tests\Feature\Http\Teachers;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\Institute;
use App\Models\TeacherAssignment;
use App\Services\TeacherAssignmentService;
use App\Support\LocalClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * POST /api/v1/institutes/{uuid}/teacher-assignments (contract v0.6 §6.2; FD-3, FD-4, FD-9).
 */
class TeacherAssignmentTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileFor();

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])->assertUnauthorized();
        $this->assertDatabaseCount('teacher_institute', 0);
    }

    public function test_unrelated_callers_get_404_even_for_a_suspended_institute(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileFor();
        $this->loginAs($this->adminOf($this->makeInstitute()));

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])
            ->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        // Authorization runs before validation: an invalid body is still a 404.
        $this->spa('POST', $this->url($institute), ['teacher_uuid' => 'nope'])->assertNotFound();

        $this->suspend($institute);
        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])->assertNotFound();

        $this->assertDatabaseCount('teacher_institute', 0);
    }

    public function test_staff_and_teacher_only_callers_get_403(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileFor();

        foreach ([$this->staffOf($institute, 'manager'), $this->teacherAt($institute)] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])
                ->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertSame(1, TeacherAssignment::query()->count());
    }

    public function test_an_admin_assigns_a_teacher_without_any_role_row_and_it_is_audited(): void
    {
        $institute = $this->makeInstitute(null, ['name' => 'Fictional Assign Academy']);
        $admin = $this->adminOf($institute);
        $teacherUser = $this->makeUser(['name' => 'Fictional Teacher']);
        $teacher = $this->teacherProfileFor($teacherUser);
        $rolesBefore = DB::table('user_roles')->count();
        $this->loginAs($admin);

        $response = $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])
            ->assertCreated()
            ->assertExactJson(['data' => [
                'teacher' => [
                    'uuid' => $teacher->uuid,
                    'display_name' => 'Mr. Fictional Teacher',
                    'headline' => null,
                    'bio' => null,
                    'years_experience' => 0,
                ],
                'institute_uuid' => $institute->uuid,
                'status' => 'active',
                'joined_on' => '2026-10-20',
                'left_on' => null,
            ]]);
        $this->assertNoInternalIds($response);

        // Teacher access is derived from the assignment only: no user_roles row is ever written.
        $this->assertSame($rolesBefore, DB::table('user_roles')->count());
        $this->assertDatabaseMissing('user_roles', ['user_id' => $teacherUser->getKey()]);

        $rows = $this->auditRows(AuditAction::TEACHER_ASSIGNED);
        $this->assertCount(1, $rows);
        $this->assertEquals($admin->getKey(), $rows[0]['user_id']);
        $this->assertEquals($institute->getKey(), $rows[0]['institute_id']);
        $this->assertSame('teacher_assignment', $rows[0]['auditable_type']);
        $this->assertSame('127.0.0.1', $rows[0]['ip_address']);
        $this->assertEquals([
            'teacher_uuid' => $teacher->uuid, 'institute_uuid' => $institute->uuid, 'status' => 'active', 'joined_on' => '2026-10-20',
        ], $rows[0]['new_values']);

        // The teacher now sees the institute, labelled `teacher`.
        $this->loginAs($teacherUser);
        $this->spa('GET', '/api/v1/me/institutes')->assertOk()
            ->assertJsonPath('data.0.institute.uuid', $institute->uuid)
            ->assertJsonPath('data.0.my_access', ['teacher']);
    }

    public function test_super_admin_may_assign_even_to_a_suspended_institute(): void
    {
        $institute = $this->makeInstitute();
        $this->suspend($institute);
        $this->loginAs($this->superAdmin());

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => '2026-10-20'])
            ->assertCreated();
    }

    public function test_an_admin_of_a_suspended_institute_gets_403_institute_suspended(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $this->suspend($institute);
        $this->loginAs($admin);

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => '2026-10-20'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This institute is suspended.', 'reason' => 'institute_suspended']);
        $this->assertDatabaseCount('teacher_institute', 0);
    }

    public function test_invalid_input_is_rejected_with_422(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileFor();
        $this->loginAs($this->adminOf($institute));

        $cases = [
            ['teacher_uuid', ['teacher_uuid' => '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', 'joined_on' => '2026-10-20']],
            ['teacher_uuid', ['teacher_uuid' => 'not-a-uuid', 'joined_on' => '2026-10-20']],
            ['teacher_uuid', ['joined_on' => '2026-10-20']],
            ['joined_on', ['teacher_uuid' => $teacher->uuid]],
            ['joined_on', ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-02-30']],
            ['joined_on', ['teacher_uuid' => $teacher->uuid, 'joined_on' => '20/10/2026']],
            ['status', ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20', 'status' => 'former']],
            ['user_id', ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20', 'user_id' => 1]],
        ];

        foreach ($cases as [$field, $body]) {
            $this->spa('POST', $this->url($institute), $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('teacher_institute', 0);
    }

    public function test_joined_on_may_be_today_or_earlier_in_colombo_but_not_in_the_future(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        $today = Carbon::now(LocalClock::TIMEZONE);

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => $today->toDateString()])
            ->assertCreated()->assertJsonPath('data.joined_on', $today->toDateString());
        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => $today->copy()->subDay()->toDateString()])
            ->assertCreated();

        $future = $this->teacherProfileFor();
        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $future->uuid, 'joined_on' => $today->copy()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('joined_on');

        $this->assertSame(2, TeacherAssignment::query()->count());
        $this->assertDatabaseMissing('teacher_institute', ['teacher_id' => $future->getKey()]);
    }

    public function test_an_assignment_made_today_can_be_ended_today(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        $today = LocalClock::today();

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => $today])->assertCreated();

        app(TeacherAssignmentService::class)->end(TeacherAssignment::query()->sole(), $today);

        $this->assertSame('former', TeacherAssignment::query()->sole()->status);
    }

    public function test_a_malformed_teacher_uuid_is_refused_before_any_teacher_lookup(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => "x' OR 1=1 --", 'joined_on' => '2026-10-20'])
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_uuid');

        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('teacher_profiles', $query['query']);
        }
    }

    public function test_a_second_active_assignment_is_409_already_active_and_a_former_one_does_not_block(): void
    {
        $institute = $this->makeInstitute();
        $teacher = $this->teacherProfileFor();
        $this->insertAssignment($teacher->getKey(), $institute->getKey(), ['status' => 'former', 'joined_on' => '2025-01-01', 'left_on' => '2025-12-31']);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-20'])->assertCreated();
        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $teacher->uuid, 'joined_on' => '2026-10-21'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'already_active');

        $this->assertSame(1, TeacherAssignment::query()->where('status', 'active')->count());
        $this->assertCount(1, $this->auditRows(AuditAction::TEACHER_ASSIGNED));
    }

    public function test_a_failed_audit_insert_rolls_the_assignment_back(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->url($institute), ['teacher_uuid' => $this->teacherProfileFor()->uuid, 'joined_on' => '2026-10-20'])
            ->assertStatus(500);

        $this->assertDatabaseCount('teacher_institute', 0);
    }

    private function url(Institute $institute): string
    {
        return "/api/v1/institutes/{$institute->uuid}/teacher-assignments";
    }
}
