<?php

namespace App\Policies\Concerns;

use App\Authorization\TenantAccess;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;

/**
 * Membership and suspension checks shared by the guardian policies (same rules as InstitutePolicy).
 *
 * @property-read TenantAccess $access
 */
trait ChecksInstituteMembership
{
    /**
     * The institute to check: the locked row a service passes in, or a fresh read.
     */
    private function instituteFor(int $instituteId, ?Institute $locked): Institute
    {
        return $locked ?? Institute::query()->findOrFail($instituteId);
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
}
