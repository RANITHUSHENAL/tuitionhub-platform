<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Models\User;
use App\Support\Transactions;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * PATCH /api/v1/me (contract v0.5 §4.5; FD-1, FD-2). The caller's own name only.
 */
class UserProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Sets the caller's name (already normalized and validated). An unchanged name writes nothing
     * and records no audit row. The audit row never contains the name itself.
     *
     * @throws AuthorizationException the account was suspended after the request's first check
     */
    public function updateName(User $user, string $name): User
    {
        return Transactions::retrying(function () use ($user, $name) {
            // Pattern U (D-022): only the caller's own users row is locked.
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($user->status !== 'active') {
                throw new AuthorizationException('This account is suspended.');
            }

            if ($user->name === $name) {
                return $user;
            }

            $wasSet = $user->name !== null;

            $user->forceFill(['name' => $name])->save();

            $this->audit->record(
                AuditAction::USER_PROFILE_UPDATED,
                $user,
                null,
                ['name_was_set' => $wasSet],
                ['user_uuid' => $user->uuid, 'field' => 'name', 'onboarding_completed' => ! $wasSet],
            );

            return $user;
        });
    }
}
