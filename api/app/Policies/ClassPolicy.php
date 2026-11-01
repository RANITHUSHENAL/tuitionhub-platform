<?php

namespace App\Policies;

use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Models\User;
use App\Policies\Concerns\ChecksInstituteMembership;
use Illuminate\Auth\Access\Response;

/**
 * Class endpoints (contract v0.8 §7.3, §7.4, §8.3, §8.4; D-026). The institute is always the
 * class's institute.
 *
 * Who may see a class's private data: super_admin, the institute's admins and active staff, and
 * the assigned teacher while their assignment there is active (TenantAccess::isAssignedTeacher).
 *
 * Visibility comes first for every ability (D-026): a caller who cannot see the class gets 404,
 * whatever they asked for, including other teachers at the same institute; a caller who can see
 * it but may not act gets 403; an allowed caller at a suspended institute (not super_admin) gets
 * 403 `institute_suspended`. Services pass the locked institute row and authorize again inside
 * their transaction.
 */
class ClassPolicy
{
    use ChecksInstituteMembership;

    public function __construct(private readonly TenantAccess $access) {}

    /**
     * The full `Class` (§7.3). Everyone else sees at most the public shape, decided by the controller.
     */
    public function view(User $user, TuitionClass $class): Response
    {
        return $this->canSeePrivateData($user, $class) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * PATCH /classes/{uuid} (§7.4): the institute's admins, or super_admin (also while suspended).
     * Staff and the assigned teacher: 403. Anyone who cannot see the class: 404.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function update(User $user, TuitionClass $class, ?Institute $institute = null): Response
    {
        if ($this->access->isSuperAdmin($user)) {
            return Response::allow();
        }

        $institute = $this->instituteFor($class->institute_id, $institute);

        if (! $this->canSeePrivateData($user, $class, $institute)) {
            return Response::denyAsNotFound();
        }

        if (! $this->access->isInstituteAdmin($user, $institute)) {
            return Response::deny();
        }

        $this->assertActive($institute);

        return Response::allow();
    }

    /**
     * GET /classes/{uuid}/enrollments (§8.4): super_admin, admins, active staff and the assigned
     * teacher. Anyone else: 404 (the roster is never revealed).
     */
    public function viewEnrollments(User $user, TuitionClass $class): Response
    {
        return $this->view($user, $class);
    }

    /**
     * POST /classes/{uuid}/enrollments (§8.3), and withdrawals (§8.5): the institute's admins and
     * active staff. super_admin (not a member) and the assigned teacher: 403. Anyone who cannot
     * see the class: 404.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function enroll(User $user, TuitionClass $class, ?Institute $institute = null): Response
    {
        $institute = $this->instituteFor($class->institute_id, $institute);

        if (! $this->canSeePrivateData($user, $class, $institute)) {
            return Response::denyAsNotFound();
        }

        if (! $this->isMember($user, $institute)) {
            return Response::deny();
        }

        $this->assertActive($institute);

        return Response::allow();
    }

    private function canSeePrivateData(User $user, TuitionClass $class, ?Institute $institute = null): bool
    {
        if ($this->access->isSuperAdmin($user) || $this->access->isAssignedTeacher($user, $class)) {
            return true;
        }

        return $this->isMember($user, $this->instituteFor($class->institute_id, $institute));
    }

    private function isMember(User $user, Institute $institute): bool
    {
        return $this->access->isInstituteAdmin($user, $institute) || $this->access->isInstituteStaff($user, $institute);
    }
}
