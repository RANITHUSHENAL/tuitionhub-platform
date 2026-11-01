<?php

namespace Tests\Feature\Http\Enrollments;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\ClassEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/enrollments/{uuid}/withdraw (contract v0.8 §8.5; D-024 VP-81, VP-82; D-026).
 * Today is 2026-11-10.
 */
class WithdrawEnrollmentTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_unauthenticated_requests_get_401(): void
    {
        $enrollment = $this->enrol($this->makeClass($this->makeInstitute()));

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertUnauthorized();
    }

    public function test_staff_withdraw_an_enrolment_which_keeps_its_history_and_frees_the_place(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute, ['max_students' => 1]);
        $enrollment = $this->enrol($class, overrides: ['enrolled_on' => '2026-09-01']);
        $staff = $this->staffOf($institute);
        $this->loginAs($staff);

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => '2026-11-05'])
            ->assertOk()
            ->assertJsonPath('data.uuid', $enrollment->uuid)
            ->assertJsonPath('data.status', 'withdrawn')
            ->assertJsonPath('data.enrolled_on', '2026-09-01')
            ->assertJsonPath('data.withdrawn_on', '2026-11-05');

        $rows = $this->auditRows(AuditAction::ENROLLMENT_WITHDRAWN);
        $this->assertCount(1, $rows);
        $this->assertEquals($staff->getKey(), $rows[0]['user_id']);
        $this->assertSame(['status' => 'active'], $rows[0]['old_values']);
        $this->assertSame('withdrawn', $rows[0]['new_values']['status']);
        $this->assertSame('2026-11-05', $rows[0]['new_values']['withdrawn_on']);

        // The place is free again, and the old row stays.
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($institute)->uuid])->assertCreated();
        $this->assertSame(2, ClassEnrollment::query()->where('class_id', $class->getKey())->count());
    }

    public function test_a_withdrawal_before_enrolled_on_becomes_a_zero_length_enrolment(): void
    {
        $institute = $this->makeInstitute();
        $enrollment = $this->enrol($this->makeClass($institute), overrides: ['enrolled_on' => '2026-12-01']);
        $this->loginAs($this->adminOf($institute));

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])
            ->assertOk()
            ->assertJsonPath('data.enrolled_on', '2026-12-01')
            ->assertJsonPath('data.withdrawn_on', '2026-12-01');

        $this->assertNull($enrollment->fresh()->active_flag);
    }

    public function test_a_withdrawal_that_would_invalidate_recorded_attendance_is_409(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $enrollment = $this->enrol($class, overrides: ['enrolled_on' => '2026-09-01']);
        $session = $this->insertSession($class->getKey(), ['session_date' => '2026-11-03']);
        $this->insertAttendance($session, $enrollment->getKey(), $class->getKey());
        $this->loginAs($this->adminOf($institute));

        foreach (['2026-11-03', '2026-10-01'] as $date) {
            $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => $date])
                ->assertStatus(409)
                ->assertExactJson(['message' => 'Attendance is already recorded on or after this date.', 'reason' => 'attendance_recorded']);
        }

        $this->assertSame('active', $enrollment->fresh()->status);

        // The day after the last attended session is fine.
        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => '2026-11-04'])->assertOk()->assertJsonPath('data.withdrawn_on', '2026-11-04');
    }

    public function test_only_an_active_enrolment_can_be_withdrawn_and_the_date_is_validated(): void
    {
        $institute = $this->makeInstitute();
        $enrollment = $this->enrol($this->makeClass($institute));
        $this->loginAs($this->adminOf($institute));

        foreach (['2026-11-11', '2026-02-30', '10/11/2026', null] as $date) {
            $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => $date])->assertUnprocessable()->assertJsonValidationErrors('withdrawn_on');
        }

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY, 'status' => 'withdrawn'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertOk();
        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])
            ->assertStatus(409)->assertJsonPath('reason', 'not_active');
        $this->assertCount(1, $this->auditRows(AuditAction::ENROLLMENT_WITHDRAWN));
    }

    public function test_permissions_match_enrolling(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $enrollment = $this->enrol($class);

        $this->loginAs($this->adminOf($this->makeInstitute()));
        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertNotFound();

        foreach ([$this->teacherUserOf($class), $this->superAdmin()] as $user) {
            $this->loginAs($user);
            $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertForbidden();
        }

        $staff = $this->staffOf($institute);
        $this->suspend($institute);
        $this->loginAs($staff);
        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertForbidden()->assertJsonPath('reason', 'institute_suspended');

        $this->spa('POST', '/api/v1/enrollments/not-a-uuid/withdraw', ['withdrawn_on' => self::TODAY])->assertNotFound();
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_a_failed_audit_insert_rolls_the_withdrawal_back(): void
    {
        $institute = $this->makeInstitute();
        $enrollment = $this->enrol($this->makeClass($institute));
        $this->loginAs($this->adminOf($institute));
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('Fictional audit outage.'));

        $this->spa('POST', $this->url($enrollment), ['withdrawn_on' => self::TODAY])->assertStatus(500);
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    private function url(ClassEnrollment $enrollment): string
    {
        return "/api/v1/enrollments/{$enrollment->uuid}/withdraw";
    }
}
