<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-61, VP-67, VP-69, VP-70, VP-71 (attendance) and VP-73/VP-74 (attendance): attendances
 * constraints (docs/SPRINT_SCHEMA.md G6.1, D-024). Eligibility on session_date, cancelled and
 * future sessions, and corrections are service rules (E3).
 */
class AttendanceConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp61_a_session_and_an_enrolment_from_different_classes_can_never_be_combined(): void
    {
        $institute = $this->insertInstitute();
        $classX = $this->insertClass($institute);
        $classY = $this->insertClass($institute);
        $session = $this->insertSession($classX);
        $enrollment = $this->insertEnrollment($classY, $this->insertStudentProfile($institute), $institute);

        // Tagged with the session's class: the enrolment belongs to another class.
        $this->assertConstraintViolation(
            fn () => $this->insertAttendance($session, $enrollment, $classX),
            self::NO_REFERENCED_ROW,
            'attendances_enrollment_class_fk',
        );

        // Tagged with the enrolment's class: the session belongs to another class.
        $this->assertConstraintViolation(
            fn () => $this->insertAttendance($session, $enrollment, $classY),
            self::NO_REFERENCED_ROW,
            'attendances_session_class_fk',
        );

        // A third class matches neither.
        $this->assertConstraintViolation(
            fn () => $this->insertAttendance($session, $enrollment, $this->insertClass($institute)),
            self::NO_REFERENCED_ROW,
            'attendances_',
        );

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_vp69_a_second_mark_for_the_same_session_and_enrolment_is_rejected(): void
    {
        [$session, $enrollment, $class] = $this->sessionAndEnrollment();
        $this->insertAttendance($session, $enrollment, $class);

        $this->assertConstraintViolation(
            fn () => $this->insertAttendance($session, $enrollment, $class, ['status' => 'late']),
            self::DUPLICATE_KEY,
            'attendances_session_enrollment_unique',
        );

        // A correction updates the one row in place.
        DB::table('attendances')->where('session_id', $session)->update(['status' => 'late']);
        $this->assertSame('late', DB::table('attendances')->sole()->status);
    }

    public function test_vp67_a_cancelled_session_keeps_its_attendance_and_cannot_be_deleted(): void
    {
        [$session, $enrollment, $class] = $this->sessionAndEnrollment();
        $this->insertAttendance($session, $enrollment, $class);
        DB::table('class_sessions')->where('id', $session)->update(['status' => 'cancelled']);

        $this->assertConstraintViolation(fn () => DB::table('class_sessions')->where('id', $session)->delete(), self::ROW_IS_REFERENCED, 'attendances_session_class_fk');
        $this->assertConstraintViolation(fn () => DB::table('classes')->where('id', $class)->delete(), self::ROW_IS_REFERENCED, '_class');

        $this->assertSame('cancelled', DB::table('class_sessions')->where('id', $session)->value('status'));
        $this->assertSame(1, DB::table('attendances')->where('session_id', $session)->count());
    }

    public function test_vp70_attendance_survives_a_withdrawal_and_the_enrolment_cannot_be_deleted(): void
    {
        [$session, $enrollment, $class] = $this->sessionAndEnrollment();
        $attendance = $this->insertAttendance($session, $enrollment, $class, ['status' => 'absent', 'notes' => 'Fictional note.']);
        $before = (array) DB::table('attendances')->where('id', $attendance)->first();

        $this->withdrawEnrollment($enrollment, '2026-12-01');

        $this->assertEquals($before, (array) DB::table('attendances')->where('id', $attendance)->first());
        $this->assertConstraintViolation(fn () => DB::table('class_enrollments')->where('id', $enrollment)->delete(), self::ROW_IS_REFERENCED, 'attendances_enrollment_class_fk');

        // A re-enrolment is a new row; the old attendance stays with the old enrolment.
        $student = (int) DB::table('class_enrollments')->where('id', $enrollment)->value('student_id');
        $institute = (int) DB::table('class_enrollments')->where('id', $enrollment)->value('institute_id');
        $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => '2027-01-01']);
        $this->assertSame($enrollment, (int) DB::table('attendances')->where('id', $attendance)->value('enrollment_id'));
    }

    public function test_vp71_attendance_check_constraints_and_the_notes_limit(): void
    {
        [$session, $enrollment, $class] = $this->sessionAndEnrollment();

        $this->assertConstraintViolation(fn () => $this->insertAttendance($session, $enrollment, $class, ['status' => 'sick']), self::CHECK_VIOLATED, 'attendances_status_chk');
        $this->assertConstraintViolation(fn () => $this->insertAttendance($session, $enrollment, $class, ['method' => 'qr']), self::CHECK_VIOLATED, 'attendances_method_chk');
        // Strict mode: an over-long note is an error, never silently truncated.
        $this->assertConstraintViolation(fn () => $this->insertAttendance($session, $enrollment, $class, ['notes' => str_repeat('n', 501)]), 1406, 'notes');

        foreach (['present', 'absent', 'late', 'excused'] as $index => $status) {
            $other = $this->insertSession($class, ['session_date' => '2026-11-1'.($index + 4)]);
            $this->insertAttendance($other, $enrollment, $class, ['status' => $status, 'notes' => str_repeat('n', 500)]);
        }

        $this->assertSame(4, DB::table('attendances')->count());
    }

    public function test_vp73_vp74_attendance_parents_must_exist_and_the_marker_cannot_be_deleted(): void
    {
        [$session, $enrollment, $class] = $this->sessionAndEnrollment();
        $marker = $this->insertUser();
        $this->insertAttendance($session, $enrollment, $class, ['marked_by_user_id' => $marker]);

        $this->assertConstraintViolation(fn () => DB::table('users')->where('id', $marker)->delete(), self::ROW_IS_REFERENCED, 'attendances_marked_by_fk');
        $this->assertConstraintViolation(fn () => $this->insertAttendance(999999999, $enrollment, $class), self::NO_REFERENCED_ROW, 'attendances_session_class_fk');
        $this->assertConstraintViolation(fn () => $this->insertAttendance($this->insertSession($class, ['session_date' => '2026-11-05']), 999999999, $class), self::NO_REFERENCED_ROW, 'attendances_enrollment_class_fk');
        $this->assertConstraintViolation(
            fn () => $this->insertAttendance($this->insertSession($class, ['session_date' => '2026-11-06']), $enrollment, $class, ['marked_by_user_id' => 999999999]),
            self::NO_REFERENCED_ROW,
            'attendances_marked_by_fk',
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int} session, enrolment and their shared class
     */
    private function sessionAndEnrollment(): array
    {
        $institute = $this->insertInstitute();
        $class = $this->insertClass($institute);

        return [
            $this->insertSession($class),
            $this->insertEnrollment($class, $this->insertStudentProfile($institute), $institute),
            $class,
        ];
    }
}
