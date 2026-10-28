<?php

namespace Tests\Feature\Authorization;

use App\Authorization\TenantAccess;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Services\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-19, VP-22 and the contract §3 access rules, re-derived from the database on every call.
 */
class TenantAccessTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_super_admin_is_global_only(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();
        $this->insertUserRole($user->getKey(), Role::SUPER_ADMIN, null);

        $this->assertTrue($this->access()->isSuperAdmin($user));
        $this->assertFalse($this->access()->isInstituteAdmin($user, $institute));
    }

    public function test_vp22_an_institute_role_grants_nothing_at_another_institute(): void
    {
        $user = $this->makeUser();
        $instituteA = $this->makeInstitute();
        $instituteB = $this->makeInstitute();
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $instituteA->getKey());
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_STAFF, $instituteA->getKey());
        $this->insertStaff($instituteA->getKey(), $user->getKey());

        $this->assertTrue($this->access()->isInstituteAdmin($user, $instituteA));
        $this->assertTrue($this->access()->isInstituteStaff($user, $instituteA));
        $this->assertFalse($this->access()->isInstituteAdmin($user, $instituteB));
        $this->assertFalse($this->access()->isInstituteStaff($user, $instituteB));
        $this->assertFalse($this->access()->isManager($user, $instituteB));
        $this->assertFalse($this->access()->isSuperAdmin($user));
    }

    public function test_vp19_staff_access_needs_both_the_role_and_an_active_staff_row(): void
    {
        $institute = $this->makeInstitute();

        $roleOnly = $this->makeUser();
        $this->insertUserRole($roleOnly->getKey(), Role::INSTITUTE_STAFF, $institute->getKey());

        $rowOnly = $this->makeUser();
        $this->insertStaff($institute->getKey(), $rowOnly->getKey());

        $formerWithRole = $this->makeUser();
        $this->insertUserRole($formerWithRole->getKey(), Role::INSTITUTE_STAFF, $institute->getKey());
        $this->insertStaff($institute->getKey(), $formerWithRole->getKey(), ['status' => 'former', 'left_on' => '2026-10-10']);

        $this->assertFalse($this->access()->isInstituteStaff($roleOnly, $institute));
        $this->assertFalse($this->access()->isInstituteStaff($rowOnly, $institute));
        $this->assertFalse($this->access()->isInstituteStaff($formerWithRole, $institute));
    }

    public function test_manager_means_admin_or_active_staff_with_position_manager(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->makeUser();
        $manager = $this->makeUser();
        $clerk = $this->makeUser();
        $this->insertUserRole($admin->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        foreach ([[$manager, 'manager'], [$clerk, 'clerk']] as [$user, $position]) {
            $this->insertUserRole($user->getKey(), Role::INSTITUTE_STAFF, $institute->getKey());
            $this->insertStaff($institute->getKey(), $user->getKey(), ['position' => $position]);
        }

        $this->assertTrue($this->access()->isManager($admin, $institute));
        $this->assertTrue($this->access()->isManager($manager, $institute));
        $this->assertFalse($this->access()->isManager($clerk, $institute));
    }

    public function test_teaching_institutes_list_only_active_assignments(): void
    {
        $user = $this->makeUser();
        $teacher = $this->insertTeacherProfile(['user_id' => $user->getKey()]);
        $active = $this->makeInstitute();
        $former = $this->makeInstitute();
        $this->insertAssignment($teacher, $active->getKey());
        $this->insertAssignment($teacher, $former->getKey(), ['status' => 'former', 'left_on' => '2026-10-10']);

        $this->assertSame([$active->getKey()], $this->access()->activeTeachingInstitutes($user)->modelKeys());
        $this->assertTrue($this->access()->isActiveTeacherAt($user, $active));
        $this->assertFalse($this->access()->isActiveTeacherAt($user, $former));
    }

    public function test_guardian_access_needs_an_active_link_to_that_student(): void
    {
        $institute = $this->insertInstitute();
        $guardianUser = $this->makeUser();
        $guardian = $this->insertGuardianProfile(['user_id' => $guardianUser->getKey()]);
        $linked = StudentProfile::query()->findOrFail($this->insertStudentProfile($institute));
        $revoked = StudentProfile::query()->findOrFail($this->insertStudentProfile($institute));
        $unrelated = StudentProfile::query()->findOrFail($this->insertStudentProfile($institute));
        $this->insertGuardianLink($linked->getKey(), $guardian);
        $this->revokeGuardianLink($this->insertGuardianLink($revoked->getKey(), $guardian));

        $this->assertTrue($this->access()->hasActiveGuardianLink($guardianUser, $linked));
        $this->assertFalse($this->access()->hasActiveGuardianLink($guardianUser, $revoked));
        $this->assertFalse($this->access()->hasActiveGuardianLink($guardianUser, $unrelated));
    }

    public function test_suspended_users_are_denied_everywhere(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();
        $this->insertUserRole($user->getKey(), Role::SUPER_ADMIN, null);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());
        $teacher = $this->insertTeacherProfile(['user_id' => $user->getKey()]);
        $this->insertAssignment($teacher, $institute->getKey());

        // Suspended in the database; the in-memory model is deliberately stale.
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        $this->assertFalse($this->access()->isSuperAdmin($user));
        $this->assertFalse($this->access()->isInstituteAdmin($user, $institute));
        $this->assertFalse($this->access()->isActiveTeacherAt($user, $institute));
        $this->assertCount(0, $this->access()->activeTeachingInstitutes($user));
    }

    public function test_nothing_is_cached_between_calls(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();
        $access = $this->access();
        app(RoleAssignmentService::class)->grant($user, Role::INSTITUTE_ADMIN, $institute);
        $this->assertTrue($access->isInstituteAdmin($user, $institute));

        app(RoleAssignmentService::class)->revoke($user, Role::INSTITUTE_ADMIN, $institute);

        $this->assertFalse($access->isInstituteAdmin($user, $institute));
    }

    private function access(): TenantAccess
    {
        return app(TenantAccess::class);
    }
}
