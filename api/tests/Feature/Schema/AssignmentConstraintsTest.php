<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-10 to VP-14: one active staff row or teacher assignment, with history kept and dates consistent.
 */
class AssignmentConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp10_a_second_active_staff_row_for_the_same_user_and_institute_is_rejected(): void
    {
        $institute = $this->insertInstitute();
        $user = $this->insertUser();
        $this->insertStaff($institute, $user);

        $this->assertConstraintViolation(
            fn () => $this->insertStaff($institute, $user, ['position' => 'clerk']),
            self::DUPLICATE_KEY,
            'institute_staff_active_unique',
        );
    }

    public function test_vp11_several_former_staff_rows_and_one_active_row_are_accepted(): void
    {
        $institute = $this->insertInstitute();
        $user = $this->insertUser();

        $this->insertStaff($institute, $user, ['status' => 'former', 'left_on' => '2026-10-05']);
        $this->insertStaff($institute, $user, ['status' => 'former', 'left_on' => '2026-10-10']);
        $this->insertStaff($institute, $user);

        $this->assertSame(3, DB::table('institute_staff')->where('user_id', $user)->count());
        $this->assertSame(1, DB::table('institute_staff')->where('user_id', $user)->where('active_flag', 1)->count());
    }

    public function test_vp12_staff_status_and_left_on_must_agree(): void
    {
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertStaff($institute, $this->insertUser(), ['status' => 'former', 'left_on' => null]),
            self::CHECK_VIOLATED,
            'institute_staff_status_left_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertStaff($institute, $this->insertUser(), ['status' => 'active', 'left_on' => '2026-10-20']),
            self::CHECK_VIOLATED,
            'institute_staff_status_left_chk',
        );
    }

    public function test_vp12_assignment_status_and_left_on_must_agree(): void
    {
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertAssignment($this->insertTeacherProfile(), $institute, ['status' => 'former', 'left_on' => null]),
            self::CHECK_VIOLATED,
            'teacher_institute_status_left_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertAssignment($this->insertTeacherProfile(), $institute, ['status' => 'active', 'left_on' => '2026-10-20']),
            self::CHECK_VIOLATED,
            'teacher_institute_status_left_chk',
        );
    }

    public function test_vp13_a_second_active_assignment_for_the_same_teacher_and_institute_is_rejected(): void
    {
        $teacher = $this->insertTeacherProfile();
        $institute = $this->insertInstitute();
        $this->insertAssignment($teacher, $institute);

        $this->assertConstraintViolation(
            fn () => $this->insertAssignment($teacher, $institute),
            self::DUPLICATE_KEY,
            'teacher_institute_active_unique',
        );
    }

    public function test_vp13_former_assignments_are_kept_alongside_a_new_active_one(): void
    {
        $teacher = $this->insertTeacherProfile();
        $institute = $this->insertInstitute();

        $this->insertAssignment($teacher, $institute, ['status' => 'former', 'left_on' => '2026-10-10']);
        $this->insertAssignment($teacher, $institute);

        $this->assertSame(2, DB::table('teacher_institute')->where('teacher_id', $teacher)->count());
    }

    public function test_vp14_left_on_cannot_be_before_joined_on(): void
    {
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertStaff($institute, $this->insertUser(), ['status' => 'former', 'joined_on' => '2026-10-10', 'left_on' => '2026-10-09']),
            self::CHECK_VIOLATED,
            'institute_staff_dates_chk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertAssignment($this->insertTeacherProfile(), $institute, ['status' => 'former', 'joined_on' => '2026-10-10', 'left_on' => '2026-10-09']),
            self::CHECK_VIOLATED,
            'teacher_institute_dates_chk',
        );
    }
}
