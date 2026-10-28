<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of user_roles (D-011; docs/SPRINT_SCHEMA.md G2.2). role_scope always comes from
 * the roles row, never from input.
 *
 * Lock order shared by all services: users → institutes → institute_staff / teacher_institute
 * → user_roles. Only existing rows are locked; inserts rely on the unique keys.
 */
class RoleAssignmentService
{
    private const UNIQUE_GRANT_KEY = 'user_roles_user_role_scope_unique';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws DomainRuleException UNKNOWN_ROLE, ROLE_SCOPE_MISMATCH, ROLE_ALREADY_GRANTED
     */
    public function grant(User $user, string $roleName, ?Institute $institute = null): UserRole
    {
        $role = $this->role($roleName);
        $this->assertScope($role, $institute);

        return DB::transaction(function () use ($user, $role, $institute) {
            $userRole = new UserRole;
            $userRole->forceFill([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'role_scope' => $role->scope,
                'institute_id' => $institute?->getKey(),
            ]);

            try {
                $userRole->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), self::UNIQUE_GRANT_KEY)) {
                    throw new DomainRuleException(DomainRuleException::ROLE_ALREADY_GRANTED, $exception);
                }

                throw $exception;
            }

            $this->audit->record(AuditAction::ROLE_GRANTED, $userRole, $institute, [], $this->snapshot($role, $user, $institute));

            return $userRole;
        });
    }

    /**
     * @throws DomainRuleException UNKNOWN_ROLE, ROLE_SCOPE_MISMATCH, ROLE_NOT_GRANTED, OWNER_ADMIN_REVOCATION_FORBIDDEN
     */
    public function revoke(User $user, string $roleName, ?Institute $institute = null): void
    {
        $role = $this->role($roleName);
        $this->assertScope($role, $institute);

        DB::transaction(function () use ($user, $role, $institute) {
            $lockedInstitute = $institute === null
                ? null
                : Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            $userRole = UserRole::query()
                ->where('user_id', $user->getKey())
                ->where('role_id', $role->getKey())
                ->where('institute_id', $institute?->getKey())
                ->lockForUpdate()
                ->first();

            if ($userRole === null) {
                throw new DomainRuleException(DomainRuleException::ROLE_NOT_GRANTED);
            }

            // D-011: the owner always holds institute_admin for their institute (VP-26).
            if ($role->name === Role::INSTITUTE_ADMIN && (int) $lockedInstitute->owner_user_id === (int) $user->getKey()) {
                throw new DomainRuleException(DomainRuleException::OWNER_ADMIN_REVOCATION_FORBIDDEN);
            }

            $userRole->delete();

            $this->audit->record(AuditAction::ROLE_REVOKED, $userRole, $institute, $this->snapshot($role, $user, $institute));
        });
    }

    private function role(string $roleName): Role
    {
        // Exact match only: the collation would otherwise accept any casing.
        if (! in_array($roleName, Role::NAMES, true)) {
            throw new DomainRuleException(DomainRuleException::UNKNOWN_ROLE);
        }

        return Role::query()->where('name', $roleName)->firstOrFail();
    }

    private function assertScope(Role $role, ?Institute $institute): void
    {
        $scoped = $institute !== null;

        if (($role->scope === Role::SCOPE_GLOBAL && $scoped) || ($role->scope === Role::SCOPE_INSTITUTE && ! $scoped)) {
            throw new DomainRuleException(DomainRuleException::ROLE_SCOPE_MISMATCH);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(Role $role, User $user, ?Institute $institute): array
    {
        return [
            'role' => $role->name,
            'user_uuid' => $user->uuid,
            'institute_uuid' => $institute?->uuid,
        ];
    }
}
