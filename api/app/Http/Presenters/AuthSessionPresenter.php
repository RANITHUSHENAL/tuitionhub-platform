<?php

namespace App\Http\Presenters;

use App\Authorization\TenantAccess;
use App\Models\GuardianProfile;
use App\Models\Institute;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\UserRole;

/**
 * Response bodies for OTP verify (contract §4.2) and GET /auth/session (§4.4).
 * Only public UUIDs are exposed. roles, teaching_at and active_child_link_count are display hints;
 * authorization is re-derived on every request.
 */
class AuthSessionPresenter
{
    public function __construct(private readonly TenantAccess $access) {}

    /**
     * @return array<string, mixed>
     */
    public function verified(User $user): array
    {
        return [
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'phone' => $user->phone,
                'roles' => $this->roles($user, withNames: false),
                'teaching_at' => $this->teachingAt($user),
                'active_child_link_count' => $this->access->activeChildLinkCount($user),
            ],
            'onboarding_required' => $user->name === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function session(User $user): array
    {
        return [
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
            'roles' => $this->roles($user, withNames: true),
            'teaching_at' => $this->teachingAt($user),
            'active_child_link_count' => $this->access->activeChildLinkCount($user),
            'has_teacher_profile' => TeacherProfile::query()->where('user_id', $user->getKey())->exists(),
            'has_guardian_profile' => GuardianProfile::query()->where('user_id', $user->getKey())->exists(),
            'onboarding_required' => $user->name === null,
        ];
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function roles(User $user, bool $withNames): array
    {
        return $this->access->storedRoles($user)
            ->map(function (UserRole $userRole) use ($withNames) {
                $role = ['role' => $userRole->role->name, 'institute_uuid' => $userRole->institute?->uuid];

                return $withNames ? $role + ['institute_name' => $userRole->institute?->name] : $role;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{institute_uuid: string, institute_name: string}>
     */
    private function teachingAt(User $user): array
    {
        return $this->access->activeTeachingInstitutes($user)
            ->map(fn (Institute $institute) => ['institute_uuid' => $institute->uuid, 'institute_name' => $institute->name])
            ->values()
            ->all();
    }
}
