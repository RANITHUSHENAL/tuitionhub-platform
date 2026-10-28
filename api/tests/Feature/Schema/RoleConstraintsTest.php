<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Schema\Concerns\SchemaFixtures;
use Tests\TestCase;

/**
 * VP-2 to VP-8: the database itself enforces role uniqueness and role/scope consistency.
 */
class RoleConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use SchemaFixtures;

    public function test_vp2_a_second_global_super_admin_row_for_the_same_user_is_rejected(): void
    {
        $user = $this->insertUser();
        $this->insertUserRole($user, 'super_admin', null);

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'super_admin', null),
            self::DUPLICATE_KEY,
            'user_roles_user_role_scope_unique',
        );
    }

    public function test_vp3_a_duplicate_institute_role_at_the_same_institute_is_rejected(): void
    {
        $user = $this->insertUser();
        $institute = $this->insertInstitute();
        $this->insertUserRole($user, 'institute_admin', $institute);

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'institute_admin', $institute),
            self::DUPLICATE_KEY,
            'user_roles_user_role_scope_unique',
        );
    }

    public function test_vp4_the_same_role_at_two_institutes_is_accepted(): void
    {
        $user = $this->insertUser();

        $this->insertUserRole($user, 'institute_admin', $this->insertInstitute());
        $this->insertUserRole($user, 'institute_admin', $this->insertInstitute());

        $this->assertSame(2, DB::table('user_roles')->where('user_id', $user)->count());
    }

    public function test_vp5_a_super_admin_row_with_an_institute_is_rejected(): void
    {
        $user = $this->insertUser();
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'super_admin', $institute),
            self::CHECK_VIOLATED,
            'user_roles_scope_chk',
        );
    }

    public function test_vp6_an_institute_role_without_an_institute_is_rejected(): void
    {
        $user = $this->insertUser();

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'institute_staff', null),
            self::CHECK_VIOLATED,
            'user_roles_scope_chk',
        );
    }

    public function test_vp7_a_role_scope_that_disagrees_with_the_role_is_rejected(): void
    {
        $user = $this->insertUser();
        $institute = $this->insertInstitute();

        $this->assertConstraintViolation(
            fn () => $this->insertUserRole($user, 'super_admin', $institute, ['role_scope' => 'institute']),
            self::NO_REFERENCED_ROW,
            'user_roles_role_scope_fk',
        );
    }

    public function test_vp8_a_fourth_role_cannot_be_inserted(): void
    {
        $this->assertConstraintViolation(
            fn () => DB::table('roles')->insert(['name' => 'teacher', 'scope' => 'institute', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]),
            self::CHECK_VIOLATED,
            'roles_name_scope_chk',
        );
    }

    public function test_vp8_the_scope_of_a_referenced_role_cannot_be_changed(): void
    {
        $this->insertUserRole($this->insertUser(), 'super_admin', null);

        $this->assertConstraintViolation(
            fn () => DB::table('roles')->where('name', 'super_admin')->update(['scope' => 'institute']),
            self::CHECK_VIOLATED,
            'roles_name_scope_chk',
        );

        $this->assertSame('global', DB::table('roles')->where('name', 'super_admin')->value('scope'));
    }
}
