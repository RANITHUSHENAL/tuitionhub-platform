<?php

namespace Tests\Feature\Services;

use App\Audit\AuditAction;
use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\UserRole;
use App\Services\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * VP-9 and VP-26: RoleAssignmentService is the only writer of user_roles.
 */
class RoleAssignmentServiceTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_a_global_super_admin_is_granted_and_audited(): void
    {
        $user = $this->makeUser();

        $userRole = $this->service()->grant($user, Role::SUPER_ADMIN);

        $this->assertSame('global', UserRole::query()->findOrFail($userRole->getKey())->role_scope);
        $audit = AuditLog::query()->where('action', AuditAction::ROLE_GRANTED)->sole();
        $this->assertSame('user_role', $audit->auditable_type);
        $this->assertEquals(['role' => 'super_admin', 'user_uuid' => $user->uuid, 'institute_uuid' => null], $audit->new_values);
    }

    public function test_an_institute_role_is_granted_with_its_institute(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();

        $userRole = $this->service()->grant($user, Role::INSTITUTE_STAFF, $institute)->refresh();

        $this->assertSame('institute', $userRole->role_scope);
        $this->assertEquals($institute->getKey(), $userRole->institute_id);
    }

    public function test_vp9_role_and_scope_mismatches_are_rejected_without_writing(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();

        $this->assertDomainRule(DomainRuleException::ROLE_SCOPE_MISMATCH, fn () => $this->service()->grant($user, Role::SUPER_ADMIN, $institute));
        $this->assertDomainRule(DomainRuleException::ROLE_SCOPE_MISMATCH, fn () => $this->service()->grant($user, Role::INSTITUTE_STAFF));

        foreach (['teacher', 'guardian', 'SUPER_ADMIN', 'Institute_Admin'] as $name) {
            $this->assertDomainRule(DomainRuleException::UNKNOWN_ROLE, fn () => $this->service()->grant($user, $name, $institute));
        }

        $this->assertSame(0, UserRole::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_granting_an_existing_role_throws_role_already_granted(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();
        $this->service()->grant($user, Role::INSTITUTE_ADMIN, $institute);

        $this->assertDomainRule(DomainRuleException::ROLE_ALREADY_GRANTED, fn () => $this->service()->grant($user, Role::INSTITUTE_ADMIN, $institute));

        $this->assertSame(1, UserRole::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::ROLE_GRANTED)->count());
    }

    public function test_a_role_is_revoked_and_audited(): void
    {
        $user = $this->makeUser();
        $institute = $this->makeInstitute();
        $this->service()->grant($user, Role::INSTITUTE_STAFF, $institute);

        $this->service()->revoke($user, Role::INSTITUTE_STAFF, $institute);

        $this->assertSame(0, UserRole::query()->count());
        $audit = AuditLog::query()->where('action', AuditAction::ROLE_REVOKED)->sole();
        $this->assertEquals(['role' => 'institute_staff', 'user_uuid' => $user->uuid, 'institute_uuid' => $institute->uuid], $audit->old_values);
    }

    public function test_revoking_a_role_that_is_not_granted_is_rejected(): void
    {
        $this->assertDomainRule(
            DomainRuleException::ROLE_NOT_GRANTED,
            fn () => $this->service()->revoke($this->makeUser(), Role::INSTITUTE_ADMIN, $this->makeInstitute()),
        );
    }

    public function test_vp26_the_owners_institute_admin_role_cannot_be_revoked(): void
    {
        $owner = $this->makeUser();
        $institute = $this->makeInstitute($owner);
        $otherAdmin = $this->makeUser();
        $this->service()->grant($owner, Role::INSTITUTE_ADMIN, $institute);
        $this->service()->grant($otherAdmin, Role::INSTITUTE_ADMIN, $institute);

        $this->assertDomainRule(
            DomainRuleException::OWNER_ADMIN_REVOCATION_FORBIDDEN,
            fn () => $this->service()->revoke($owner, Role::INSTITUTE_ADMIN, $institute),
        );

        $this->assertSame(1, UserRole::query()->where('user_id', $owner->getKey())->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::ROLE_REVOKED)->count());

        $this->service()->revoke($otherAdmin, Role::INSTITUTE_ADMIN, $institute);
        $this->assertSame(0, UserRole::query()->where('user_id', $otherAdmin->getKey())->count());
    }

    private function service(): RoleAssignmentService
    {
        return app(RoleAssignmentService::class);
    }
}
