<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\GuardianProfile;
use App\Models\Institute;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Transactions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Revokes guardian links (contract v0.7 §9.4; C6; docs/SPRINT_SCHEMA.md G4.3; D-015 Q25/Q37, D-023).
 *
 * A link is never deleted or reactivated: revocation sets revoked_at and revoked_by_user_id, and
 * access through the link ends on the guardian's next request (nothing is cached). Lock order
 * (D-022 Pattern I): institutes → student_profiles → student_guardians, each by primary key; users
 * only shared (the revoked_by foreign key and the audit row).
 */
class GuardianLinkService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Revokes the active link between the student and the guardian (identified by their user). If
     * it was the primary link, the oldest remaining active link (linked_at, then id) becomes primary
     * in the same transaction; with none left, the student has no primary guardian.
     *
     * @throws AuthorizationException 403/404 from StudentPolicy::revokeGuardianLink
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     * @throws ModelNotFoundException no active link (404)
     */
    public function revoke(User $actor, StudentProfile $student, User $guardianUser): void
    {
        // A plain read: the guardian is identified, never locked.
        $guardianId = GuardianProfile::query()->where('user_id', $guardianUser->getKey())->value('id');

        Transactions::retrying(function () use ($actor, $student, $guardianUser, $guardianId) {
            $institute = Institute::query()->whereKey($student->managing_institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('revokeGuardianLink', [$student, $institute]);

            // Q25: the student row is locked for every primary-guardian change.
            $student = StudentProfile::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            $linkId = $guardianId === null ? null : StudentGuardian::query()->active()
                ->where('student_id', $student->getKey())
                ->where('guardian_id', $guardianId)
                ->value('id');

            $link = $linkId === null ? null : StudentGuardian::query()->whereKey($linkId)->lockForUpdate()->first();

            if ($link === null || $link->revoked_at !== null) {
                throw (new ModelNotFoundException)->setModel(StudentGuardian::class);
            }

            $wasPrimary = $link->is_primary;

            // Revoke first: that frees the primary slot (primary_flag) before any promotion.
            $link->forceFill([
                'revoked_at' => CarbonImmutable::now('UTC')->startOfSecond(),
                'revoked_by_user_id' => $actor->getKey(),
            ])->save();

            $promoted = $wasPrimary ? $this->promoteOldestActiveLink($student) : null;

            $this->audit->record(
                AuditAction::GUARDIAN_LINK_REVOKED,
                $link,
                $institute,
                $wasPrimary ? ['primary_link_id' => $link->getKey()] : [],
                [
                    'student_uuid' => $student->uuid,
                    'guardian_uuid' => $guardianUser->uuid,
                    'relationship' => $link->relationship,
                    ...($wasPrimary ? ['primary_link_id' => $promoted?->getKey()] : []),
                ],
            );
        });
    }

    private function promoteOldestActiveLink(StudentProfile $student): ?StudentGuardian
    {
        $nextId = StudentGuardian::query()->active()
            ->where('student_id', $student->getKey())
            ->orderBy('linked_at')
            ->orderBy('id')
            ->value('id');

        if ($nextId === null) {
            return null;
        }

        $next = StudentGuardian::query()->whereKey($nextId)->lockForUpdate()->firstOrFail();
        $next->forceFill(['is_primary' => true])->save();

        return $next;
    }
}
