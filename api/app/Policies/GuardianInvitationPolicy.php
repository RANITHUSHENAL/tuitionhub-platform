<?php

namespace App\Policies;

use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\GuardianInvitation;
use App\Models\Institute;
use App\Models\User;
use App\Policies\Concerns\ChecksInstituteMembership;
use Illuminate\Auth\Access\Response;

/**
 * POST /guardian-invitations/{uuid}/revoke (contract v0.7 §9.3; E2-5, E2-9; D-023).
 */
class GuardianInvitationPolicy
{
    use ChecksInstituteMembership;

    public function __construct(private readonly TenantAccess $access) {}

    /**
     * super_admin (also while the institute is suspended); any admin of the invitation's institute;
     * or the inviter while still active staff there. Other staff: 403. Everyone else, including an
     * inviter who has left the institute and teacher-only callers: 404.
     *
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function revoke(User $user, GuardianInvitation $invitation, ?Institute $institute = null): Response
    {
        if ($this->access->isSuperAdmin($user)) {
            return Response::allow();
        }

        $institute = $this->instituteFor($invitation->institute_id, $institute);

        if ($this->access->isInstituteAdmin($user, $institute)) {
            $this->assertActive($institute);

            return Response::allow();
        }

        if (! $this->access->isInstituteStaff($user, $institute)) {
            return Response::denyAsNotFound();
        }

        if ((int) $invitation->invited_by_user_id !== (int) $user->getKey()) {
            return Response::deny();
        }

        $this->assertActive($institute);

        return Response::allow();
    }
}
