<?php

namespace App\Authorization;

use App\Models\Institute;
use App\Models\InstituteStaff;
use App\Models\Role;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central tenant authorization (contract v0.3 §3; D-011).
 *
 * Every call re-queries the database; nothing is cached, so revocations and endings take effect on
 * the next check. Every institute-scoped check takes the institute explicitly, so a role at one
 * institute never grants access at another. Suspended users are denied everywhere.
 * Institute suspension is handled by the endpoint policies.
 */
class TenantAccess
{
    public function isSuperAdmin(User $user): bool
    {
        return $this->isActiveUser($user) && $this->hasRole($user, Role::SUPER_ADMIN, null);
    }

    public function isInstituteAdmin(User $user, Institute $institute): bool
    {
        return $this->isActiveUser($user) && $this->hasRole($user, Role::INSTITUTE_ADMIN, $institute);
    }

    /**
     * Staff access needs BOTH the institute_staff role and an active institute_staff row.
     */
    public function isInstituteStaff(User $user, Institute $institute): bool
    {
        return $this->isActiveUser($user)
            && $this->hasRole($user, Role::INSTITUTE_STAFF, $institute)
            && $this->activeStaff($user, $institute)->exists();
    }

    /**
     * Institute admin, or institute staff whose active row has position manager (contract §3).
     */
    public function isManager(User $user, Institute $institute): bool
    {
        return $this->isInstituteAdmin($user, $institute)
            || ($this->isInstituteStaff($user, $institute)
                && $this->activeStaff($user, $institute)->where('position', 'manager')->exists());
    }

    /**
     * Institutes where the user has an active teacher assignment. Class data additionally needs
     * the class assignment (contract §3), checked by the class policies.
     *
     * @return Collection<int, Institute>
     */
    public function activeTeachingInstitutes(User $user): Collection
    {
        if (! $this->isActiveUser($user)) {
            return new Collection;
        }

        return Institute::query()
            ->whereIn('id', $this->activeAssignments($user)->select('institute_id'))
            ->orderBy('id')
            ->get();
    }

    public function isActiveTeacherAt(User $user, Institute $institute): bool
    {
        return $this->isActiveUser($user)
            && $this->activeAssignments($user)->where('institute_id', $institute->getKey())->exists();
    }

    public function hasActiveGuardianLink(User $user, StudentProfile $student): bool
    {
        return $this->isActiveUser($user)
            && StudentGuardian::query()
                ->active()
                ->where('student_id', $student->getKey())
                ->whereHas('guardian', fn (Builder $guardian) => $guardian->where('user_id', $user->getKey()))
                ->exists();
    }

    /**
     * Re-read from the database: an in-memory model may be stale.
     */
    private function isActiveUser(User $user): bool
    {
        return User::query()->whereKey($user->getKey())->where('status', 'active')->exists();
    }

    private function hasRole(User $user, string $roleName, ?Institute $institute): bool
    {
        return UserRole::query()
            ->where('user_id', $user->getKey())
            ->where('institute_id', $institute?->getKey())
            ->whereHas('role', fn (Builder $role) => $role->where('name', $roleName))
            ->exists();
    }

    /**
     * @return Builder<InstituteStaff>
     */
    private function activeStaff(User $user, Institute $institute): Builder
    {
        return InstituteStaff::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->where('institute_id', $institute->getKey());
    }

    /**
     * @return Builder<TeacherAssignment>
     */
    private function activeAssignments(User $user): Builder
    {
        return TeacherAssignment::query()
            ->active()
            ->whereHas('teacher', fn (Builder $teacher) => $teacher->where('user_id', $user->getKey()));
    }
}
