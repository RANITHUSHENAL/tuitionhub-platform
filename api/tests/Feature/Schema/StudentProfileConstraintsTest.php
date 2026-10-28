<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-50 (students): student_profiles parents, the optional unique user account, and institute protection.
 */
class StudentProfileConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_vp50a_student_parents_must_exist(): void
    {
        $institute = $this->insertInstitute();

        $cases = [
            'student_profiles_institute_fk' => fn () => $this->insertStudentProfile(self::MISSING_ID),
            'student_profiles_created_by_fk' => fn () => $this->insertStudentProfile($institute, ['created_by_user_id' => self::MISSING_ID]),
            'student_profiles_user_fk' => fn () => $this->insertStudentProfile($institute, ['user_id' => self::MISSING_ID]),
            'student_profiles_grade_fk' => fn () => $this->insertStudentProfile($institute, ['grade_id' => self::MISSING_ID]),
            'student_profiles_medium_fk' => fn () => $this->insertStudentProfile($institute, ['medium_id' => self::MISSING_ID]),
            'student_profiles_exam_target_fk' => fn () => $this->insertStudentProfile($institute, ['exam_target_id' => self::MISSING_ID]),
        ];

        foreach ($cases as $constraint => $insert) {
            $this->assertConstraintViolation($insert, self::NO_REFERENCED_ROW, $constraint);
        }
    }

    public function test_vp50b_user_id_is_unique_but_may_be_null_many_times(): void
    {
        $institute = $this->insertInstitute();
        $this->insertStudentProfile($institute);
        $this->insertStudentProfile($institute);

        $user = $this->insertUser();
        $this->insertStudentProfile($institute, ['user_id' => $user]);

        $this->assertSame(2, DB::table('student_profiles')->whereNull('user_id')->count());

        $this->assertConstraintViolation(
            fn () => $this->insertStudentProfile($institute, ['user_id' => $user]),
            self::DUPLICATE_KEY,
            'student_profiles_user_id_unique',
        );
    }

    public function test_vp50c_an_institute_with_students_cannot_be_deleted(): void
    {
        $institute = $this->insertInstitute();
        $this->insertStudentProfile($institute);

        $this->assertConstraintViolation(
            fn () => DB::table('institutes')->where('id', $institute)->delete(),
            self::ROW_IS_REFERENCED,
            'student_profiles_institute_fk',
        );
    }
}
