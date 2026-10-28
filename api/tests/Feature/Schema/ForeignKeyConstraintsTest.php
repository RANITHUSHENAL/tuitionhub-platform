<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-15 and VP-16: children need existing parents, and referenced parents cannot be deleted.
 */
class ForeignKeyConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    private const MISSING_ID = 999999999;

    public function test_vp15_child_rows_must_reference_existing_parents(): void
    {
        $user = $this->insertUser();
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole(self::MISSING_ID, 'institute_admin', $institute),
            self::NO_REFERENCED_ROW,
            'user_roles_user_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'institute_admin', self::MISSING_ID),
            self::NO_REFERENCED_ROW,
            'user_roles_institute_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'super_admin', null, ['role_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'user_roles_role_scope_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertAssignment(self::MISSING_ID, $institute),
            self::NO_REFERENCED_ROW,
            'teacher_institute_teacher_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertInstitute(['owner_user_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'institutes_owner_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertTeacherProfile(['user_id' => self::MISSING_ID]),
            self::NO_REFERENCED_ROW,
            'teacher_profiles_user_fk',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertStaff($institute, self::MISSING_ID),
            self::NO_REFERENCED_ROW,
            'institute_staff_user_fk',
        );
    }

    public function test_vp16_a_referenced_user_cannot_be_deleted(): void
    {
        $user = $this->insertUser();
        $this->insertTeacherProfile(['user_id' => $user]);

        $this->assertConstraintViolation(
            fn () => DB::table('users')->where('id', $user)->delete(),
            self::ROW_IS_REFERENCED,
            'teacher_profiles_user_fk',
        );
    }

    public function test_vp16_a_referenced_role_cannot_be_deleted(): void
    {
        $this->insertUserRole($this->insertUser(), 'super_admin', null);

        $this->assertConstraintViolation(
            fn () => DB::table('roles')->where('name', 'super_admin')->delete(),
            self::ROW_IS_REFERENCED,
            'user_roles_role_scope_fk',
        );
    }

    public function test_vp16_a_referenced_institute_cannot_be_deleted(): void
    {
        $institute = $this->insertInstitute();
        $this->insertUserRole($this->insertUser(), 'institute_admin', $institute);

        $this->assertConstraintViolation(
            fn () => DB::table('institutes')->where('id', $institute)->delete(),
            self::ROW_IS_REFERENCED,
            'user_roles_institute_fk',
        );
    }
}
