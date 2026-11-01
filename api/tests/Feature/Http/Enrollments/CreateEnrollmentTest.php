<?php

namespace Tests\Feature\Http\Enrollments;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\ClassEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/classes/{uuid}/enrollments (contract v0.8 §8.3; D-024; D-026). Today is 2026-11-10.
 */
class CreateEnrollmentTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $class = $this->makeClass($this->makeInstitute());

        $this->spa('POST', $this->enrollmentsUrl($class), [])->assertUnauthorized();
    }

    public function test_unrelated_callers_get_404_and_teachers_and_super_admin_get_403(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $student = $this->makeStudent($institute);

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid])->assertNotFound();

        foreach ([$this->teacherUserOf($class), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid])->assertForbidden()->assertJsonMissingPath('reason');
        }

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_staff_enrol_a_student_and_the_audit_row_has_uuids_only(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['title' => 'Fictional Maths']);
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        $response = $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid, 'enrolled_on' => '2026-11-01'])
            ->assertCreated()->assertHeader('Cache-Control', 'no-store, private');

        $enrollment = ClassEnrollment::query()->sole();
        $this->assertSame(['data' => [
            'uuid' => $enrollment->uuid,
            'status' => 'active',
            'enrolled_on' => '2026-11-01',
            'withdrawn_on' => null,
            'student' => ['uuid' => $student->uuid, 'full_name' => $student->full_name],
            'class' => ['uuid' => $class->uuid, 'title' => 'Fictional Maths'],
        ]], $response->json());
        $this->assertNoInternalIds($response);
        $this->assertEquals($institute->getKey(), $enrollment->institute_id);

        $rows = $this->auditRows(AuditAction::ENROLLMENT_CREATED);
        $this->assertCount(1, $rows);
        $this->assertEquals($staff->getKey(), $rows[0]['user_id']);
        $this->assertSame('class_enrollment', $rows[0]['auditable_type']);
        $this->assertEquals(['enrollment_uuid' => $enrollment->uuid, 'class_uuid' => $class->uuid, 'student_uuid' => $student->uuid, 'status' => 'active', 'enrolled_on' => '2026-11-01'], $rows[0]['new_values']);
        $this->assertStringNotContainsString($student->full_name, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_enrolled_on_defaults_to_today_and_may_be_in_the_future_within_the_class_dates(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['starts_on' => '2026-11-01', 'ends_on' => '2027-03-31']);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid])
            ->assertCreated()->assertJsonPath('data.enrolled_on', self::TODAY);
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid, 'enrolled_on' => '2027-03-31'])
            ->assertCreated()->assertJsonPath('data.status', 'active');

        foreach (['2026-10-31', '2027-04-01', '2026-02-30', '10/11/2026'] as $date) {
            $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid, 'enrolled_on' => $date])
                ->assertUnprocessable()->assertJsonValidationErrors('enrolled_on');
        }

        $this->assertSame(2, ClassEnrollment::query()->count());
    }

    public function test_only_an_active_class_takes_enrolments(): void
    {
        $institute = $this->makeInstitute();
        $this->loginAs($this->adminOf($institute));

        foreach (['draft', 'archived'] as $status) {
            $class = $this->makeClass($institute, ['status' => $status]);
            $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid])
                ->assertStatus(409)->assertExactJson(['message' => 'This record is not active.', 'reason' => 'not_active']);
        }

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_students_of_other_institutes_and_unknown_students_are_404_and_malformed_uuids_422(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));

        foreach ([$this->makeStudent($this->makeInstitute())->uuid, '6b0e3f9a-1c2d-4e5f-8a9b-0c1d2e3f4a5b'] as $uuid) {
            $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $uuid])->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        }

        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => 'not-a-uuid'])->assertUnprocessable()->assertJsonValidationErrors('student_uuid');
        $this->spa('POST', $this->enrollmentsUrl($class), [])->assertUnprocessable()->assertJsonValidationErrors('student_uuid');
        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_a_duplicate_active_enrolment_and_a_full_class_are_409(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['max_students' => 2]);
        $first = $this->makeStudent($institute);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $first->uuid])->assertCreated();
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $first->uuid])
            ->assertStatus(409)->assertJsonPath('reason', 'already_active');

        // A future enrolment takes a place at once (D-024).
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid, 'enrolled_on' => '2026-12-01'])->assertCreated();
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid])
            ->assertStatus(409)->assertExactJson(['message' => 'This class is full.', 'reason' => 'class_full']);

        $this->assertSame(2, ClassEnrollment::query()->count());
    }

    public function test_re_enrolling_after_a_withdrawal_creates_a_new_enrolment(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $student = $this->makeStudent($institute);
        $old = $this->enrol($class, $student);
        $this->withdrawEnrollment($old->getKey(), '2026-11-05');
        $this->loginAs($this->adminOf($institute));

        $uuid = $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid])->assertCreated()->json('data.uuid');

        $this->assertNotSame($old->uuid, $uuid);
        $this->assertSame('withdrawn', $old->fresh()->status);
        $this->assertSame(2, ClassEnrollment::query()->where('student_id', $student->getKey())->count());
    }

    public function test_a_suspended_institute_refuses_enrolments_and_unknown_fields_are_rejected(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $staff = $this->staffOf($institute);
        $student = $this->makeStudent($institute);
        $this->loginAs($staff);

        foreach (['institute_id' => 1, 'status' => 'withdrawn', 'class_uuid' => $class->uuid] as $field => $value) {
            $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->suspend($institute);
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid])
            ->assertForbidden()->assertJsonPath('reason', 'institute_suspended');
        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_a_failed_audit_insert_rolls_the_enrolment_back(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid])->assertStatus(500);
        $this->assertDatabaseCount('class_enrollments', 0);
    }
}
