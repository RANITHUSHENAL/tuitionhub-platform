<?php

namespace Tests\Feature\Services;

use App\Audit\AuditAction;
use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\InstituteStaff;
use App\Models\Role;
use App\Models\UserRole;
use App\Services\RoleAssignmentService;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-18 and VP-21 (staff): staff rows and the institute_staff role change together, atomically.
 */
class StaffServiceTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_adding_staff_writes_the_row_the_role_and_the_audits(): void
    {
        $institute = $this->makeInstitute();
        $user = $this->makeUser();

        $staff = $this->service()->add($institute, $user, 'manager', '2026-10-01')->refresh();

        $this->assertSame('active', $staff->status);
        $this->assertSame(1, $staff->active_flag);
        $this->assertTrue($this->access()->isInstituteStaff($user, $institute));
        $this->assertTrue($this->access()->isManager($user, $institute));
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::STAFF_ADDED)->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::ROLE_GRANTED)->count());
    }

    public function test_invalid_positions_dates_and_duplicates_are_rejected(): void
    {
        $institute = $this->makeInstitute();
        $user = $this->makeUser();

        $this->assertDomainRule(DomainRuleException::UNKNOWN_POSITION, fn () => $this->service()->add($institute, $user, 'Manager', '2026-10-01'));
        $this->assertDomainRule(DomainRuleException::INVALID_DATE, fn () => $this->service()->add($institute, $user, 'clerk', '2026-02-30'));
        $this->assertDomainRule(DomainRuleException::INVALID_DATE, fn () => $this->service()->add($institute, $user, 'clerk', '01/10/2026'));

        $this->service()->add($institute, $user, 'clerk', '2026-10-01');
        $this->assertDomainRule(DomainRuleException::ALREADY_ACTIVE, fn () => $this->service()->add($institute, $user, 'clerk', '2026-10-02'));

        $this->assertSame(1, InstituteStaff::query()->count());
        $this->assertSame(1, UserRole::query()->count());
    }

    public function test_vp18_ending_staff_removes_access_immediately_and_keeps_history(): void
    {
        $institute = $this->makeInstitute();
        $user = $this->makeUser();
        $staff = $this->service()->add($institute, $user, 'clerk', '2026-10-01');
        $this->assertTrue($this->access()->isInstituteStaff($user, $institute));

        $this->service()->end($staff, '2026-10-20');

        $this->assertFalse($this->access()->isInstituteStaff($user, $institute));
        $ended = InstituteStaff::query()->findOrFail($staff->getKey());
        $this->assertSame('former', $ended->status);
        $this->assertSame('2026-10-20', $ended->left_on->toDateString());
        $this->assertSame(0, UserRole::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::STAFF_ENDED)->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::ROLE_REVOKED)->count());
    }

    public function test_ending_staff_without_the_role_fails_with_no_partial_change(): void
    {
        $institute = $this->insertInstitute();
        $staffId = $this->insertStaff($institute, $this->insertUser());

        $this->assertDomainRule(
            DomainRuleException::ROLE_NOT_GRANTED,
            fn () => $this->service()->end(InstituteStaff::query()->findOrFail($staffId), '2026-10-20'),
        );

        $staff = InstituteStaff::query()->findOrFail($staffId);
        $this->assertSame('active', $staff->status);
        $this->assertNull($staff->left_on);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_ending_requires_an_active_row_and_a_valid_left_on(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->service()->add($institute, $this->makeUser(), 'clerk', '2026-10-10');

        $this->assertDomainRule(DomainRuleException::INVALID_DATE, fn () => $this->service()->end($staff, '2026-10-09'));
        $this->assertSame('active', InstituteStaff::query()->findOrFail($staff->getKey())->status);

        $this->service()->end($staff, '2026-10-20');
        $this->assertDomainRule(DomainRuleException::NOT_ACTIVE, fn () => $this->service()->end($staff, '2026-10-21'));
    }

    public function test_ending_staff_keeps_a_separate_institute_admin_role(): void
    {
        $institute = $this->makeInstitute();
        $user = $this->makeUser();
        app(RoleAssignmentService::class)->grant($user, Role::INSTITUTE_ADMIN, $institute);
        $staff = $this->service()->add($institute, $user, 'manager', '2026-10-01');

        $this->service()->end($staff, '2026-10-20');

        $this->assertTrue($this->access()->isInstituteAdmin($user, $institute));
        $this->assertFalse($this->access()->isInstituteStaff($user, $institute));
    }

    public function test_vp21_staff_can_be_added_again_after_ending(): void
    {
        $institute = $this->makeInstitute();
        $user = $this->makeUser();
        $this->service()->end($this->service()->add($institute, $user, 'clerk', '2026-10-01'), '2026-10-10');

        $this->service()->add($institute, $user, 'clerk', '2026-10-15');

        $this->assertTrue($this->access()->isInstituteStaff($user, $institute));
        $this->assertSame(2, InstituteStaff::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(1, InstituteStaff::query()->where('user_id', $user->getKey())->where('status', 'former')->count());
    }

    private function service(): StaffService
    {
        return app(StaffService::class);
    }

    private function access(): TenantAccess
    {
        return app(TenantAccess::class);
    }
}
