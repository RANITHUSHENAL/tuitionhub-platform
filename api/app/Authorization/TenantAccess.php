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
     * Whether the account is active right now (re-read; suspended users are rejected with 403).
     */
    public function isActive(User $user): bool
    {
        return $this->isActiveUser($user);
    }

    /**
     * The caller's stored user_roles rows only (contract §4.4); teacher and guardian never appear.
     *
     * @return Collection<int, UserRole>
     */
    public function storedRoles(User $user): Collection
    {
        return UserRole::query()
            ->with(['role', 'institute'])
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get();
    }

    /**
     * Active student_guardians links where the user is the guardian (contract §4.4).
     */
    public function activeChildLinkCount(User $user): int
    {
        if (! $this->isActiveUser($user)) {
            return 0;
        }

        return StudentGuardian::query()
            ->active()
            ->whereHas('guardian', fn (Builder $guardian) => $guardian->where('user_id', $user->getKey()))
            ->count();
    }

    /**
     * Institutes listed by GET /me/institutes (contract §5.3): a stored institute-scoped role, or
     * an active teacher assignment. The global super_admin role lists nothing here. Suspended
     * institutes are included (their status is shown). Ordered by name, then id.
     *
     * @return Builder<Institute>
     */
    public function myInstitutes(User $user): Builder
    {
        if (! $this->isActiveUser($user)) {
            return Institute::query()->whereRaw('1 = 0');
        }

        return Institute::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', UserRole::query()
                    ->select('institute_id')
                    ->where('user_id', $user->getKey())
                    ->whereNotNull('institute_id'))
                ->orWhereIn('id', $this->activeAssignments($user)->select('institute_id')))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * my_access labels per institute (contract §5.3): each stored institute role, plus `teacher`
     * for an active assignment. Two queries for the whole page.
     *
     * @param  iterable<Institute>  $institutes
     * @return array<int, list<string>> keyed by institute id
     */
    public function accessLabels(User $user, iterable $institutes): array
    {
        $ids = [];

        foreach ($institutes as $institute) {
            $ids[] = $institute->getKey();
        }

        $labels = array_fill_keys($ids, []);

        if ($ids === [] || ! $this->isActiveUser($user)) {
            return $labels;
        }

        $roles = UserRole::query()
            ->with('role')
            ->where('user_id', $user->getKey())
            ->whereIn('institute_id', $ids)
            ->get();

        foreach ([Role::INSTITUTE_ADMIN, Role::INSTITUTE_STAFF] as $roleName) {
            foreach ($roles as $userRole) {
                if ($userRole->role->name === $roleName) {
                    $labels[$userRole->institute_id][] = $roleName;
                }
            }
        }

        $teaching = $this->activeAssignments($user)->whereIn('institute_id', $ids)->pluck('institute_id');

        foreach ($teaching->unique() as $instituteId) {
            $labels[$instituteId][] = 'teacher';
        }

        return $labels;
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
