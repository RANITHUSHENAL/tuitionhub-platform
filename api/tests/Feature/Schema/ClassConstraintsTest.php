<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-65, VP-71 (classes), VP-73 and VP-74 (classes): classes constraints (docs/SPRINT_SCHEMA.md
 * G5.1, D-024). The active teacher assignment and status transitions are service rules (E3).
 */
class ClassConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_a_valid_class_is_accepted_in_every_status_and_mode(): void
    {
        $institute = $this->insertInstitute();

        foreach (['draft', 'active', 'archived'] as $status) {
            foreach (['in_person', 'online', 'hybrid'] as $mode) {
                $this->insertClass($institute, ['status' => $status, 'mode' => $mode, 'ends_on' => '2027-08-31', 'public_listing' => 1]);
            }
        }

        $this->assertSame(9, DB::table('classes')->count());
        $this->assertSame(9, DB::table('classes')->whereNull('stream_id')->count());
    }

    public function test_vp65_capacity_must_be_at_least_one_and_is_required(): void
    {
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(fn () => $this->insertClass($institute, ['max_students' => 0]), self::CHECK_VIOLATED, 'classes_max_students_chk');
        $this->assertConstraintViolation(fn () => $this->insertClass($institute, ['max_students' => null]), 1048, 'max_students');

        $this->insertClass($institute, ['max_students' => 1]);
        $this->assertSame(1, DB::table('classes')->count());
    }

    public function test_vp71_class_check_constraints(): void
    {
        $institute = $this->insertInstitute();

        $cases = [
            [['status' => 'open'], 'classes_status_chk'],
            [['mode' => 'remote'], 'classes_mode_chk'],
            [['starts_on' => '2026-11-01', 'ends_on' => '2026-10-31'], 'classes_dates_chk'],
            [['public_listing' => 2], 'classes_public_listing_chk'],
        ];

        foreach ($cases as [$overrides, $constraint]) {
            $this->assertConstraintViolation(fn () => $this->insertClass($institute, $overrides), self::CHECK_VIOLATED, $constraint);
        }

        // A one-day class and an open-ended class are both valid.
        $this->insertClass($institute, ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-01']);
        $this->insertClass($institute, ['ends_on' => null]);
        $this->assertSame(2, DB::table('classes')->count());
    }

    public function test_vp74_a_class_needs_an_existing_institute_teacher_and_lookups(): void
    {
        $institute = $this->insertInstitute();

        $cases = [
            ['institute_id', 'classes_institute_fk'],
            ['teacher_id', 'classes_teacher_fk'],
            ['subject_id', 'classes_subject_fk'],
            ['grade_id', 'classes_grade_fk'],
            ['stream_id', 'classes_stream_fk'],
            ['exam_target_id', 'classes_exam_target_fk'],
            ['medium_id', 'classes_medium_fk'],
            ['syllabus_id', 'classes_syllabus_fk'],
        ];

        foreach ($cases as [$column, $constraint]) {
            $this->assertConstraintViolation(
                fn () => $this->insertClass($institute, [$column => self::MISSING_ID]),
                self::NO_REFERENCED_ROW,
                $constraint,
            );
        }

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_vp73_institutes_teachers_and_lookups_used_by_a_class_cannot_be_deleted(): void
    {
        $institute = $this->insertInstitute();
        $teacher = $this->insertTeacherProfile();
        $class = $this->insertClass($institute, ['teacher_id' => $teacher]);
        $row = DB::table('classes')->where('id', $class)->first();

        $this->assertConstraintViolation(fn () => DB::table('institutes')->where('id', $institute)->delete(), self::ROW_IS_REFERENCED, 'classes_institute_fk');
        $this->assertConstraintViolation(fn () => DB::table('teacher_profiles')->where('id', $teacher)->delete(), self::ROW_IS_REFERENCED, 'classes_teacher_fk');
        $this->assertConstraintViolation(fn () => DB::table('subjects')->where('id', $row->subject_id)->delete(), self::ROW_IS_REFERENCED, 'classes_subject_fk');
        $this->assertConstraintViolation(fn () => DB::table('syllabi')->where('id', $row->syllabus_id)->delete(), self::ROW_IS_REFERENCED, 'classes_syllabus_fk');

        $this->assertSame(1, DB::table('classes')->count());
    }
}
