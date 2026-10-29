<?php

namespace App\Policies;

use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Institute-scoped endpoints (contract v0.6 §1.4, §3, §5, §6, §8; D-020, D-021).
 *
 * Checks always run in this order, so nothing is revealed to the wrong caller:
 *  1. Not related to the institute at all → 404, even when the institute is suspended.
 *  2. Related but not allowed (staff on an admin action, teacher-only caller) → 403.
 *  3. Allowed, but the institute is suspended and the caller is not super_admin → 403 with
 *     reason `institute_suspended` (writes only; admins and staff keep read access, FD-3).
 *
 * A caller is related when they are super_admin, an institute admin, institute staff (role and
 * active staff row) or an active teacher there. Every check re-queries the database (TenantAccess).
 * Services call the same abilities again on the locked row inside their transaction.
 */
class InstitutePolicy
{
    public function __construct(private readonly TenantAccess $access) {}

    /**
     * GET /institutes (§5.2): super_admin only.
     */
    public function viewAny(User $user): Response
    {
        return $this->access->isSuperAdmin($user) ? Response::allow() : Response::deny();
    }

    /**
     * GET /institutes/{uuid} (§5.4): super_admin, admins and staff. Teacher-only callers get 403 (FD-4).
     */
    public function view(User $user, Institute $institute): Response
    {
        if ($this->access->isSuperAdmin($user)
            || $this->access->isInstituteAdmin($user, $institute)
            || $this->access->isInstituteStaff($user, $institute)) {
            return Response::allow();
        }

        return $this->isActiveTeacher($user, $institute) ? Response::deny() : Response::denyAsNotFound();
    }

    /**
     * PATCH /institutes/{uuid} (§5.5): super_admin, or this institute's admin (the owner holds it).
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function update(User $user, Institute $institute): Response
    {
        if ($this->access->isSuperAdmin($user)) {
            return Response::allow();
        }

        if ($this->access->isInstituteAdmin($user, $institute)) {
            $this->assertActive($institute);

            return Response::allow();
        }

        return $this->relatedDenial($user, $institute);
    }

    /**
     * Changing `status` (§5.5): super_admin only. Anyone else related to the institute gets 403.
     */
    public function changeStatus(User $user, Institute $institute): Response
    {
        if ($this->access->isSuperAdmin($user)) {
            return Response::allow();
        }

        return $this->relatedDenial($user, $institute);
    }

    /**
     * POST /institutes/{uuid}/teacher-assignments (§6.2): super_admin, or this institute's admin.
     * Staff and teacher-only callers get 403. No user_roles row is ever created for a teacher (§3).
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function assignTeacher(User $user, Institute $institute): Response
    {
        return $this->update($user, $institute);
    }

    /**
     * GET /institutes/{uuid}/teachers (§6.3): super_admin, admins and active staff, also while the
     * institute is suspended (FD-3). Teacher-only callers get 403 (FD-4).
     */
    public function viewTeachers(User $user, Institute $institute): Response
    {
        return $this->view($user, $institute);
    }

    /**
     * POST /institutes/{uuid}/students (§8.1): this institute's admins and active staff only.
     * super_admin and teacher-only callers get 403; a suspended institute refuses the write.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function createStudent(User $user, Institute $institute): Response
    {
        if ($this->isMember($user, $institute)) {
            $this->assertActive($institute);

            return Response::allow();
        }

        return $this->memberOnlyDenial($user, $institute);
    }

    /**
     * GET /institutes/{uuid}/students (§8.2): this institute's admins and active staff only, also
     * while the institute is suspended (FD-3). super_admin and teacher-only callers get 403.
     */
    public function viewStudents(User $user, Institute $institute): Response
    {
        if ($this->isMember($user, $institute)) {
            return Response::allow();
        }

        return $this->memberOnlyDenial($user, $institute);
    }

    /**
     * Institute admin, or institute staff (role and active staff row).
     */
    private function isMember(User $user, Institute $institute): bool
    {
        return $this->access->isInstituteAdmin($user, $institute)
            || $this->access->isInstituteStaff($user, $institute);
    }

    /**
     * For student data, super_admin is related (403) but not allowed: the contract limits it to
     * same-institute staff and admins (§8.1, §8.2).
     */
    private function memberOnlyDenial(User $user, Institute $institute): Response
    {
        return $this->access->isSuperAdmin($user) ? Response::deny() : $this->relatedDenial($user, $institute);
    }

    /**
     * Writes are refused while the institute is suspended (§3), except for super_admin, which
     * callers check first.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    private function assertActive(Institute $institute): void
    {
        if ($institute->status !== Institute::STATUS_ACTIVE) {
            throw new DomainRuleException(DomainRuleException::INSTITUTE_SUSPENDED);
        }
    }

    /**
     * 403 for a caller who can know the institute exists; 404 for everyone else.
     */
    private function relatedDenial(User $user, Institute $institute): Response
    {
        $related = $this->access->isInstituteAdmin($user, $institute)
            || $this->access->isInstituteStaff($user, $institute)
            || $this->isActiveTeacher($user, $institute);

        return $related ? Response::deny() : Response::denyAsNotFound();
    }

    private function isActiveTeacher(User $user, Institute $institute): bool
    {
        return $this->access->isActiveTeacherAt($user, $institute);
    }
}
