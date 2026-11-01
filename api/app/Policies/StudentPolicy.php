<?php

namespace App\Policies;

use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Models\User;
use App\Policies\Concerns\ChecksInstituteMembership;
use Illuminate\Auth\Access\Response;

/**
 * Student guardian endpoints (contract v0.7 §9.1, §9.4; D-023). The institute is always the
 * student's managing institute.
 *
 * Order, as in InstitutePolicy: not allowed and not entitled to know the student exists → 404;
 * entitled to know but not allowed → 403; allowed but the institute is suspended (not super_admin)
 * → 403 `institute_suspended`. Teacher-only callers see no student data outside class rosters, so
 * they get 404. Services pass the locked institute row and authorize again inside their transaction.
 */
class StudentPolicy
{
    use ChecksInstituteMembership;

    public function __construct(private readonly TenantAccess $access) {}

    /**
     * GET /students/{uuid} (§8.6, E2b; D-028): the same readers as the institute's student
     * list (§8.2) — admins and active staff of the managing institute, also while the institute is
     * suspended (FD-3). super_admin is not a member: 403. Teacher-only and unrelated callers: 404,
     * so the student's existence is never revealed (as in §9.1).
     */
    public function view(User $user, StudentProfile $student): Response
    {
        $institute = $this->instituteFor($student->managing_institute_id, null);

        if ($this->access->isInstituteAdmin($user, $institute) || $this->access->isInstituteStaff($user, $institute)) {
            return Response::allow();
        }

        return $this->access->isSuperAdmin($user) ? Response::deny() : Response::denyAsNotFound();
    }

    /**
     * GET /students/{uuid}/guardians (§9.6, E2b; D-028): the same readers as view().
     */
    public function viewGuardians(User $user, StudentProfile $student): Response
    {
        return $this->view($user, $student);
    }

    /**
     * GET /students/{uuid}/guardian-invitations (§9.7, E2b; D-028): the same readers as view().
     */
    public function viewGuardianInvitations(User $user, StudentProfile $student): Response
    {
        return $this->view($user, $student);
    }

    /**
     * POST /students/{uuid}/guardian-invitations (§9.1): admins and active staff of the managing
     * institute. super_admin is not a member: 403.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function inviteGuardian(User $user, StudentProfile $student, ?Institute $institute = null): Response
    {
        $institute = $this->instituteFor($student->managing_institute_id, $institute);

        if ($this->access->isInstituteAdmin($user, $institute) || $this->access->isInstituteStaff($user, $institute)) {
            $this->assertActive($institute);

            return Response::allow();
        }

        return $this->access->isSuperAdmin($user) ? Response::deny() : Response::denyAsNotFound();
    }

    /**
     * DELETE /students/{uuid}/guardian-links/{guardianUuid} (§9.4): admins of the managing
     * institute, or super_admin (also while the institute is suspended). Staff: 403.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function revokeGuardianLink(User $user, StudentProfile $student, ?Institute $institute = null): Response
    {
        if ($this->access->isSuperAdmin($user)) {
            return Response::allow();
        }

        $institute = $this->instituteFor($student->managing_institute_id, $institute);

        if ($this->access->isInstituteAdmin($user, $institute)) {
            $this->assertActive($institute);

            return Response::allow();
        }

        return $this->access->isInstituteStaff($user, $institute) ? Response::deny() : Response::denyAsNotFound();
    }
}
