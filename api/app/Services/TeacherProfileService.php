<?php

namespace App\Services;

use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\Transactions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1; FD-6). The caller's own profile only.
 *
 * Creating a profile grants no institute access and writes no user_roles row (contract §3).
 * Not audited: the contract defines no teacher-profile event (FD-6).
 *
 * Lock order (C-1, D-022): the teacher_profiles row first, then the user row with a SHARED lock
 * only. TeacherAssignmentService holds an institute lock and a shared lock on this profile row
 * (foreign-key check) while it waits for a shared lock on its actor's user row, so an exclusive
 * users lock here could deadlock with an admin assigning themselves as a teacher.
 */
class TeacherProfileService
{
    private const ATTRIBUTES = ['display_name', 'headline', 'bio', 'years_experience'];

    private const UNIQUE_USER_KEY = 'teacher_profiles_user_id_unique';

    /**
     * Creates the profile on the first call and updates it afterwards; the UUID never changes.
     * Omitted fields stay unchanged; an explicit null clears headline or bio. An update that
     * changes nothing writes nothing.
     *
     * @param  array<string, mixed>  $attributes  validated request data
     * @return array{0: TeacherProfile, 1: bool} the profile, and whether it was created
     *
     * @throws AuthorizationException the account was suspended after the request's first check
     * @throws ValidationException display_name missing when the profile is created
     */
    public function upsert(User $user, array $attributes): array
    {
        return Transactions::retrying(function () use ($user, $attributes) {
            $profile = $this->lockedProfile($user);

            $this->assertActive($user);

            if ($profile === null) {
                return $this->create($user, $attributes);
            }

            return [$this->apply($profile, $attributes), false];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: TeacherProfile, 1: bool}
     */
    private function create(User $user, array $attributes): array
    {
        if (! array_key_exists('display_name', $attributes)) {
            throw ValidationException::withMessages(['display_name' => 'The display name field is required.']);
        }

        $profile = new TeacherProfile;
        $profile->forceFill(['user_id' => $user->getKey()]);
        $profile->fill(Arr::only($attributes, self::ATTRIBUTES));

        try {
            $profile->save();

            return [$profile, true];
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), self::UNIQUE_USER_KEY)) {
                throw $exception;
            }

            // A concurrent first call created the profile; update that row instead. A locking read,
            // not a plain one: under REPEATABLE READ a plain read could still use this transaction's
            // earlier snapshot, which does not contain the other transaction's row.
            $existing = TeacherProfile::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            return [$this->apply($existing, $attributes), false];
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function apply(TeacherProfile $profile, array $attributes): TeacherProfile
    {
        $profile->fill(Arr::only($attributes, self::ATTRIBUTES));

        if ($profile->isDirty()) {
            $profile->save();
        }

        return $profile;
    }

    /**
     * The caller's profile, locked by primary key, or null when there is none yet. A plain read
     * finds the id first: a locking read on user_id for a profile that does not exist would lock
     * the index gap, and two first-time inserts could then deadlock.
     */
    private function lockedProfile(User $user): ?TeacherProfile
    {
        $id = TeacherProfile::query()->where('user_id', $user->getKey())->value('id');

        return $id === null ? null : TeacherProfile::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * A current read (FOR SHARE) of the account status: it sees the latest committed status and
     * holds it until commit, without the exclusive users lock that caused C-1.
     *
     * @throws AuthorizationException
     */
    private function assertActive(User $user): void
    {
        $status = User::query()->whereKey($user->getKey())->sharedLock()->value('status');

        if ($status !== 'active') {
            throw new AuthorizationException('This account is suspended.');
        }
    }
}
