<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Guardians\Exceptions\GuardianInviteUnavailableException;
use App\Guardians\Exceptions\InvalidInvitationCodeException;
use App\Guardians\GuardianInviteCodes;
use App\Models\GuardianInvitation;
use App\Models\GuardianProfile;
use App\Models\Institute;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Instant;
use App\Support\PhoneMasker;
use App\Support\Transactions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Guardian invitations: create (§9.1), accept (§9.2) and revoke (§9.3) (contract v0.7; C6;
 * docs/SPRINT_SCHEMA.md G4.2–G4.3; D-015, D-023).
 *
 * Lock order (D-022 Pattern I, D-023): institutes → student_profiles → guardian_invitations →
 * guardian_profiles → student_guardians, each locked by primary key; users rows only with shared
 * locks (foreign-key checks, audit rows, the explicit FOR SHARE re-check in accept), never
 * exclusively. Sets of rows are found with plain reads after the institute lock, which every writer
 * of that institute's invitations and links holds, so no range is ever locked.
 *
 * Codes: only code_hash is stored; the plaintext code exists in memory during create and is
 * returned once. No exception thrown here contains a code, a phone or a chained database error.
 */
class GuardianInvitationService
{
    /** A code_hash collision is astronomically unlikely; each one gets a new code and transaction. */
    public const CODE_ATTEMPTS = 5;

    private const UNIQUE_CODE_KEY = 'guardian_invitations_code_hash_unique';

    private const UNIQUE_PENDING_KEY = 'guardian_invitations_pending_unique';

    private const UNIQUE_GUARDIAN_USER_KEY = 'guardian_profiles_user_id_unique';

    private const UNIQUE_ACTIVE_LINK_KEY = 'student_guardians_active_unique';

    private const UNIQUE_INVITATION_LINK_KEY = 'student_guardians_invitation_unique';

    /** Transaction outcomes that become errors only after the commit, so a lazy expiry is saved. */
    private const INVALID = 'invalid';

    private const NOT_PENDING = 'not_pending';

    public function __construct(
        private readonly GuardianInviteCodes $codes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Creates a pending invitation and returns it with its code (XXXX-XXXX). The code goes into
     * the create response only and is never stored, logged or audited.
     *
     * @param  string  $guardianPhone  E.164, already normalised by the request (E2-6)
     * @return array{0: GuardianInvitation, 1: string}
     *
     * @throws GuardianInviteUnavailableException the key is not configured (503)
     * @throws AuthorizationException 403/404 from StudentPolicy::inviteGuardian
     * @throws DomainRuleException INSTITUTE_SUSPENDED, INVITATION_PENDING
     * @throws ValidationException the inviter's own phone (E2-4)
     */
    public function create(User $actor, StudentProfile $student, #[\SensitiveParameter] string $guardianPhone, string $relationship): array
    {
        $this->codes->assertConfigured();

        for ($attempt = 1; $attempt <= self::CODE_ATTEMPTS; $attempt++) {
            // Generated outside the transaction: a deadlock retry reuses this code, and a code from
            // a rolled-back attempt was never stored or shown.
            $code = $this->codes->generate();
            $codeHash = $this->codes->hash($code);

            try {
                $invitation = Transactions::retrying(
                    fn () => $this->insertInvitation($actor, $student, $guardianPhone, $relationship, $codeHash),
                );

                return [$invitation, $this->codes->display($code)];
            } catch (UniqueConstraintViolationException $exception) {
                if (! str_contains($exception->getMessage(), self::UNIQUE_CODE_KEY)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Could not generate a unique invitation code.');
    }

    /**
     * Links the caller to the invitation's student. Every refusal about the code itself is the
     * same generic 422 (InvalidInvitationCodeException), including a phone mismatch and the
     * inviter accepting their own invitation. A lazily expired invitation is saved as expired
     * before the 422 is thrown.
     *
     * @throws GuardianInviteUnavailableException the key is not configured (503)
     * @throws InvalidInvitationCodeException
     * @throws DomainRuleException INSTITUTE_SUSPENDED (E2-2), LINK_ALREADY_ACTIVE
     * @throws AuthorizationException the caller's account was suspended meanwhile
     */
    public function accept(User $actor, #[\SensitiveParameter] string $code): StudentGuardian
    {
        $this->codes->assertConfigured();

        $normalized = $this->codes->normalize($code);

        // Malformed codes never reach the database (VP-46).
        if (! $this->codes->isWellFormed($normalized)) {
            throw new InvalidInvitationCodeException;
        }

        // Lock-free lookup of the ids only, so the transaction can lock institute → student →
        // invitation in order (D-022 D-1).
        $found = GuardianInvitation::query()
            ->where('code_hash', $this->codes->hash($normalized))
            ->first(['id', 'student_id', 'institute_id']);

        if ($found === null) {
            throw new InvalidInvitationCodeException;
        }

        $outcome = Transactions::retrying(fn () => $this->acceptLocked($actor, $found));

        if ($outcome === self::INVALID) {
            throw new InvalidInvitationCodeException;
        }

        return $outcome;
    }

    /**
     * Only a pending, unexpired invitation can be revoked; anything else is 409. A lazily expired
     * invitation is saved as expired before the 409 is thrown.
     *
     * @throws AuthorizationException 403/404 from GuardianInvitationPolicy::revoke
     * @throws DomainRuleException INSTITUTE_SUSPENDED, INVITATION_NOT_PENDING
     */
    public function revoke(User $actor, GuardianInvitation $invitation): GuardianInvitation
    {
        $outcome = Transactions::retrying(function () use ($actor, $invitation) {
            $institute = Institute::query()->whereKey($invitation->institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('revoke', [$invitation, $institute]);

            $student = StudentProfile::query()->whereKey($invitation->student_id)->lockForUpdate()->firstOrFail();
            $invitation = GuardianInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            $now = $this->now();

            if ($invitation->status === GuardianInvitation::STATUS_PENDING
                && $invitation->effectiveStatus($now) === GuardianInvitation::STATUS_EXPIRED) {
                $invitation->forceFill(['status' => GuardianInvitation::STATUS_EXPIRED])->save();

                return self::NOT_PENDING;
            }

            if ($invitation->status !== GuardianInvitation::STATUS_PENDING) {
                throw new DomainRuleException(DomainRuleException::INVITATION_NOT_PENDING);
            }

            $invitation->forceFill([
                'status' => GuardianInvitation::STATUS_REVOKED,
                'revoked_at' => $now,
                'revoked_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(AuditAction::GUARDIAN_INVITATION_REVOKED, $invitation, $institute, [
                'status' => GuardianInvitation::STATUS_PENDING,
            ], [
                'invitation_uuid' => $invitation->uuid,
                'student_uuid' => $student->uuid,
                'status' => GuardianInvitation::STATUS_REVOKED,
            ]);

            return $invitation;
        });

        if ($outcome === self::NOT_PENDING) {
            throw new DomainRuleException(DomainRuleException::INVITATION_NOT_PENDING);
        }

        return $outcome;
    }

    /**
     * One create transaction: lock the institute, authorize again, lock the student, retire an
     * expired pending invitation for the same phone, then insert.
     */
    private function insertInvitation(
        User $actor,
        StudentProfile $student,
        #[\SensitiveParameter] string $guardianPhone,
        string $relationship,
        #[\SensitiveParameter] string $codeHash,
    ): GuardianInvitation {
        $institute = Institute::query()->whereKey($student->managing_institute_id)->lockForUpdate()->firstOrFail();

        Gate::forUser($actor)->authorize('inviteGuardian', [$student, $institute]);

        $student = StudentProfile::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

        if (hash_equals((string) $actor->phone, $guardianPhone)) {
            throw ValidationException::withMessages(['guardian_phone' => 'You cannot invite your own phone number.']);
        }

        $now = $this->now();

        // At most one pending row exists (pending unique key). Plain read, then by primary key.
        $pending = GuardianInvitation::query()
            ->where('student_id', $student->getKey())
            ->where('guardian_phone', $guardianPhone)
            ->where('status', GuardianInvitation::STATUS_PENDING)
            ->get(['id', 'status', 'expires_at']);

        foreach ($pending as $existing) {
            if ($existing->effectiveStatus($now) !== GuardianInvitation::STATUS_EXPIRED) {
                throw new DomainRuleException(DomainRuleException::INVITATION_PENDING);
            }

            // Lazy expiry under the lock clears pending_flag, so the new invitation can be inserted.
            GuardianInvitation::query()->whereKey($existing->getKey())->update(['status' => GuardianInvitation::STATUS_EXPIRED]);
        }

        $invitation = new GuardianInvitation;
        $invitation->forceFill([
            'student_id' => $student->getKey(),
            'institute_id' => $institute->getKey(),
            'invited_by_user_id' => $actor->getKey(),
            'guardian_phone' => $guardianPhone,
            'relationship' => $relationship,
            'code_hash' => $codeHash,
            'status' => GuardianInvitation::STATUS_PENDING,
            'expires_at' => $now->addHours((int) config('guardian_invites.ttl_hours')),
        ]);

        try {
            $invitation->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), self::UNIQUE_PENDING_KEY)) {
                // Not chained: MySQL's duplicate-entry text contains the guardian's phone (D-022).
                throw new DomainRuleException(DomainRuleException::INVITATION_PENDING);
            }

            throw $exception;
        }

        $this->audit->record(AuditAction::GUARDIAN_INVITATION_CREATED, $invitation, $institute, [], [
            'invitation_uuid' => $invitation->uuid,
            'student_uuid' => $student->uuid,
            'institute_uuid' => $institute->uuid,
            'relationship' => $relationship,
            'guardian_phone_masked' => PhoneMasker::mask($guardianPhone),
            'status' => GuardianInvitation::STATUS_PENDING,
            'expires_at' => Instant::format($invitation->expires_at),
        ]);

        return $invitation->setRelation('student', $student);
    }

    /**
     * One accept transaction. Returns the new link, or INVALID when the invitation cannot be used
     * (committed, so a lazy expiry is kept).
     */
    private function acceptLocked(User $actor, GuardianInvitation $found): StudentGuardian|string
    {
        $institute = Institute::query()->whereKey($found->institute_id)->lockForUpdate()->firstOrFail();
        $student = StudentProfile::query()->whereKey($found->student_id)->lockForUpdate()->firstOrFail();
        $invitation = GuardianInvitation::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

        // Current reads of the caller and the inviter: shared locks only, in primary-key order.
        $userIds = array_values(array_unique([(int) $actor->getKey(), (int) $invitation->invited_by_user_id]));
        sort($userIds);
        $users = User::query()
            ->whereKey($userIds)
            ->orderBy('id')
            ->sharedLock()
            ->get()
            ->keyBy('id');
        $caller = $users->get($actor->getKey());

        if ($caller === null || $caller->status !== 'active') {
            throw new AuthorizationException('This account is suspended.');
        }

        $now = $this->now();

        if ($invitation->status !== GuardianInvitation::STATUS_PENDING) {
            return self::INVALID;
        }

        if ($invitation->effectiveStatus($now) === GuardianInvitation::STATUS_EXPIRED) {
            $invitation->forceFill(['status' => GuardianInvitation::STATUS_EXPIRED])->save();

            return self::INVALID;
        }

        // The invitation stays pending: a different account, an unverified phone, or the inviter
        // (E2-4). The caller learns nothing about which check failed.
        if ($caller->phone_verified_at === null
            || ! hash_equals((string) $invitation->guardian_phone, (string) $caller->phone)
            || (int) $caller->getKey() === (int) $invitation->invited_by_user_id) {
            return self::INVALID;
        }

        // E2-2. Checked only now, so a suspension is revealed only to the invited guardian.
        if ($institute->status !== Institute::STATUS_ACTIVE) {
            throw new DomainRuleException(DomainRuleException::INSTITUTE_SUSPENDED);
        }

        $guardian = $this->guardianProfileFor($caller);

        if (StudentGuardian::query()->active()
            ->where('student_id', $student->getKey())
            ->where('guardian_id', $guardian->getKey())
            ->exists()) {
            throw new DomainRuleException(DomainRuleException::LINK_ALREADY_ACTIVE);
        }

        // Q25: the first active guardian becomes primary; decided under the student lock.
        $isPrimary = ! StudentGuardian::query()->active()
            ->where('student_id', $student->getKey())
            ->where('is_primary', true)
            ->exists();

        $link = new StudentGuardian;
        $link->forceFill([
            'student_id' => $student->getKey(),
            'guardian_id' => $guardian->getKey(),
            'relationship' => $invitation->relationship,
            'is_primary' => $isPrimary,
            'linked_by_user_id' => $invitation->invited_by_user_id,
            'linked_at' => $now,
            'invitation_id' => $invitation->getKey(),
        ]);

        try {
            $link->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Backstops for what the locks already prevent; never chained.
            if (str_contains($exception->getMessage(), self::UNIQUE_ACTIVE_LINK_KEY)) {
                throw new DomainRuleException(DomainRuleException::LINK_ALREADY_ACTIVE);
            }

            if (str_contains($exception->getMessage(), self::UNIQUE_INVITATION_LINK_KEY)) {
                throw new InvalidInvitationCodeException;
            }

            throw $exception;
        }

        $invitation->forceFill([
            'status' => GuardianInvitation::STATUS_ACCEPTED,
            'accepted_at' => $now,
            'accepted_by_user_id' => $caller->getKey(),
        ])->save();

        $this->audit->record(AuditAction::GUARDIAN_INVITATION_ACCEPTED, $invitation, $institute, [
            'status' => GuardianInvitation::STATUS_PENDING,
        ], [
            'invitation_uuid' => $invitation->uuid,
            'student_uuid' => $student->uuid,
            'status' => GuardianInvitation::STATUS_ACCEPTED,
        ]);

        $this->audit->record(
            AuditAction::GUARDIAN_LINK_CREATED,
            $link,
            $institute,
            $isPrimary ? ['primary_link_id' => null] : [],
            [
                'student_uuid' => $student->uuid,
                'guardian_uuid' => $caller->uuid,
                'relationship' => $link->relationship,
                'is_primary' => $isPrimary,
                ...($isPrimary ? ['primary_link_id' => $link->getKey()] : []),
            ],
        );

        return $link->setRelation('student', $student);
    }

    /**
     * The caller's guardian profile, created on the first acceptance (contract §9.2). A concurrent
     * first acceptance by the same user is resolved by the unique key and a locking re-read: under
     * REPEATABLE READ a plain read could still use this transaction's snapshot.
     */
    private function guardianProfileFor(User $caller): GuardianProfile
    {
        $profile = GuardianProfile::query()->where('user_id', $caller->getKey())->first();

        if ($profile !== null) {
            return $profile;
        }

        $profile = new GuardianProfile;
        $profile->forceFill(['user_id' => $caller->getKey()]);

        try {
            $profile->save();

            return $profile;
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), self::UNIQUE_GUARDIAN_USER_KEY)) {
                throw $exception;
            }

            return GuardianProfile::query()->where('user_id', $caller->getKey())->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * DATETIME columns store whole seconds; comparing at that precision keeps accepted_at <=
     * expires_at (guardian_invitations_accept_before_expiry_chk) exact.
     */
    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfSecond();
    }
}
