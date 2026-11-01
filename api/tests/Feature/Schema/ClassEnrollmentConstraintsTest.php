<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-60, VP-62 to VP-64, VP-71 (enrolments), VP-72 (active_flag), VP-73/VP-74 (enrolments) and the
 * database half of VP-81: class_enrollments constraints (docs/SPRINT_SCHEMA.md G5.3, D-024).
 * Capacity, class status and date windows are service rules (E3).
 */
class ClassEnrollmentConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp60_a_class_and_a_student_from_different_institutes_can_never_be_enrolled_together(): void
    {
        $instituteA = $this->insertInstitute();
        $instituteB = $this->insertInstitute();
        $class = $this->insertClass($instituteA);
        $student = $this->insertStudentProfile($instituteB);

        // Tagged with the class's institute: the student's managing institute does not match.
        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $student, $instituteA),
            self::NO_REFERENCED_ROW,
            'class_enrollments_student_institute_fk',
        );

        // Tagged with the student's institute: the class's institute does not match.
        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $student, $instituteB),
            self::NO_REFERENCED_ROW,
            'class_enrollments_class_institute_fk',
        );

        // A third institute matches neither.
        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $student, $this->insertInstitute()),
            self::NO_REFERENCED_ROW,
            'class_enrollments_',
        );

        $this->assertDatabaseCount('class_enrollments', 0);

        // Same institute: accepted.
        $this->insertEnrollment($class, $this->insertStudentProfile($instituteA), $instituteA);
        $this->assertDatabaseCount('class_enrollments', 1);
    }

    public function test_vp62_a_second_active_enrolment_for_the_same_class_and_student_is_rejected(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();
        $this->insertEnrollment($class, $student, $institute);

        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => '2026-12-01']),
            self::DUPLICATE_KEY,
            'class_enrollments_active_unique',
        );
    }

    public function test_vp63_any_number_of_withdrawn_enrolments_can_sit_beside_one_active_enrolment(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();

        foreach (['2026-11-01', '2026-12-01', '2027-01-01'] as $enrolledOn) {
            $this->withdrawEnrollment(
                $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => $enrolledOn]),
                $enrolledOn === '2026-11-01' ? '2026-11-15' : $enrolledOn,
            );
        }

        $active = $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => '2027-02-01']);

        $rows = DB::table('class_enrollments')->where('class_id', $class)->where('student_id', $student);
        $this->assertSame(4, (clone $rows)->count());
        $this->assertSame(3, (clone $rows)->where('status', 'withdrawn')->whereNull('active_flag')->count());
        $this->assertSame([$active], (clone $rows)->where('active_flag', 1)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_vp64_re_enrolling_after_a_withdrawal_creates_a_new_row_and_keeps_the_old_one(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();
        $first = $this->insertEnrollment($class, $student, $institute);
        $this->withdrawEnrollment($first, '2026-12-01');
        $before = (array) DB::table('class_enrollments')->where('id', $first)->first();

        $second = $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => '2027-01-01']);

        $this->assertNotSame($first, $second);
        $this->assertNotSame($before['uuid'], DB::table('class_enrollments')->where('id', $second)->value('uuid'));
        $this->assertEquals($before, (array) DB::table('class_enrollments')->where('id', $first)->first());

        // The withdrawn row can never be reactivated while the new one is active.
        $this->assertConstraintViolation(
            fn () => DB::table('class_enrollments')->where('id', $first)->update(['status' => 'active', 'withdrawn_on' => null]),
            self::DUPLICATE_KEY,
            'class_enrollments_active_unique',
        );
    }

    public function test_vp81_a_zero_length_enrolment_is_accepted_and_was_never_active(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();
        $id = $this->insertEnrollment($class, $student, $institute, ['enrolled_on' => '2026-11-10']);

        // D-024: a withdrawal before enrolled_on is stored as withdrawn_on = enrolled_on.
        $this->withdrawEnrollment($id, '2026-11-10');

        $row = DB::table('class_enrollments')->where('id', $id)->first();
        $this->assertSame('withdrawn', $row->status);
        $this->assertSame($row->enrolled_on, $row->withdrawn_on);
        $this->assertNull($row->active_flag);
    }

    public function test_vp71_enrolment_check_constraints(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();

        $cases = [
            [['status' => 'paused'], 'class_enrollments_status_chk'],
            [['status' => 'withdrawn', 'withdrawn_on' => null], 'class_enrollments_withdrawal_chk'],
            [['status' => 'active', 'withdrawn_on' => '2026-12-01'], 'class_enrollments_withdrawal_chk'],
            [['status' => 'withdrawn', 'enrolled_on' => '2026-11-10', 'withdrawn_on' => '2026-11-09'], 'class_enrollments_dates_chk'],
        ];

        foreach ($cases as [$overrides, $constraint]) {
            $this->assertConstraintViolation(fn () => $this->insertEnrollment($class, $student, $institute, $overrides), self::CHECK_VIOLATED, $constraint);
        }

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_vp72_active_flag_cannot_be_written(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();

        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $student, $institute, ['active_flag' => 1]),
            self::GENERATED_COLUMN_WRITE,
            'active_flag',
        );
    }

    public function test_vp73_vp74_enrolment_parents_must_exist_and_cannot_be_deleted(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();
        $this->insertEnrollment($class, $student, $institute);

        $this->assertConstraintViolation(fn () => DB::table('classes')->where('id', $class)->delete(), self::ROW_IS_REFERENCED, 'class_enrollments_class_institute_fk');
        $this->assertConstraintViolation(fn () => DB::table('student_profiles')->where('id', $student)->delete(), self::ROW_IS_REFERENCED, 'class_enrollments_student_institute_fk');
        $this->assertConstraintViolation(fn () => $this->insertEnrollment(999999999, $student, $institute), self::NO_REFERENCED_ROW, 'class_enrollments_class_institute_fk');
        $this->assertConstraintViolation(fn () => $this->insertEnrollment($class, 999999999, $institute), self::NO_REFERENCED_ROW, 'class_enrollments_student_institute_fk');

        // The tenant columns cannot drift once enrolments exist (ON UPDATE NO ACTION).
        $this->assertConstraintViolation(
            fn () => DB::table('classes')->where('id', $class)->update(['institute_id' => $this->insertInstitute()]),
            self::ROW_IS_REFERENCED,
            'class_enrollments_class_institute_fk',
        );
    }

    public function test_enrolment_uuids_are_unique(): void
    {
        [$class, $student, $institute] = $this->classAndStudent();
        $uuid = (string) Str::uuid();
        $this->insertEnrollment($class, $student, $institute, ['uuid' => $uuid]);

        $this->assertConstraintViolation(
            fn () => $this->insertEnrollment($class, $this->insertStudentProfile($institute), $institute, ['uuid' => $uuid]),
            self::DUPLICATE_KEY,
            'class_enrollments_uuid_unique',
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int} class, student and their shared institute
     */
    private function classAndStudent(): array
    {
        $institute = $this->insertInstitute();

        return [$this->insertClass($institute), $this->insertStudentProfile($institute), $institute];
    }
}
