<?php

namespace App\Services;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1; FD-6). The caller's own profile only.
 *
 * Creating a profile grants no institute access and writes no user_roles row (contract §3).
 * Not audited: the contract defines no teacher-profile event (FD-6).
 */
class TeacherProfileService
{
    private const ATTRIBUTES = ['display_name', 'headline', 'bio', 'years_experience'];

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
        return DB::transaction(function () use ($user, $attributes) {
            // Lock order: users first. Serialises two first calls for the same user, so only one
            // profile is created (teacher_profiles_user_id_unique is the backstop).
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($user->status !== 'active') {
                throw new AuthorizationException('This account is suspended.');
            }

            $profile = TeacherProfile::query()->where('user_id', $user->getKey())->first();
            $created = $profile === null;

            if ($created) {
                if (! array_key_exists('display_name', $attributes)) {
                    throw ValidationException::withMessages(['display_name' => 'The display name field is required.']);
                }

                $profile = new TeacherProfile;
                $profile->forceFill(['user_id' => $user->getKey()]);
            }

            $profile->fill(Arr::only($attributes, self::ATTRIBUTES));

            if ($created || $profile->isDirty()) {
                $profile->save();
            }

            return [$profile, $created];
        });
    }
}
