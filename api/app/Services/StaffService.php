<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\InstituteStaff;
use App\Models\Role;
use App\Models\User;
use App\Support\LocalClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds and ends institute staff together with their institute_staff role, atomically
 * (docs/SPRINT_SCHEMA.md G3.2). Staff access needs both rows (contract §3).
 */
class StaffService
{
    private const UNIQUE_ACTIVE_KEY = 'institute_staff_active_unique';

    public function __construct(
        private readonly RoleAssignmentService $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws DomainRuleException UNKNOWN_POSITION, INVALID_DATE, ALREADY_ACTIVE, ROLE_ALREADY_GRANTED
     */
    public function add(Institute $institute, User $user, string $position, string $joinedOn): InstituteStaff
    {
        if (! in_array($position, InstituteStaff::POSITIONS, true)) {
            throw new DomainRuleException(DomainRuleException::UNKNOWN_POSITION);
        }

        $joinedOn = LocalClock::date($joinedOn);

        return DB::transaction(function () use ($institute, $user, $position, $joinedOn) {
            $institute = Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            $staff = new InstituteStaff;
            $staff->forceFill([
                'institute_id' => $institute->getKey(),
                'user_id' => $user->getKey(),
                'position' => $position,
                'status' => 'active',
                'joined_on' => $joinedOn,
                'left_on' => null,
            ]);

            try {
                $staff->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), self::UNIQUE_ACTIVE_KEY)) {
                    throw new DomainRuleException(DomainRuleException::ALREADY_ACTIVE, $exception);
                }

                throw $exception;
            }

            $this->roles->grant($user, Role::INSTITUTE_STAFF, $institute);

            $this->audit->record(AuditAction::STAFF_ADDED, $staff, $institute, [], [
                'user_uuid' => $user->uuid,
                'institute_uuid' => $institute->uuid,
                'position' => $position,
                'status' => 'active',
                'joined_on' => $joinedOn,
            ]);

            return $staff;
        });
    }

    /**
     * Revokes the institute_staff role first, so a missing role fails before anything changes.
     *
     * @throws DomainRuleException NOT_ACTIVE, INVALID_DATE, ROLE_NOT_GRANTED
     */
    public function end(InstituteStaff $staff, ?string $leftOn = null): void
    {
        $leftOn = LocalClock::date($leftOn ?? LocalClock::today());

        DB::transaction(function () use ($staff, $leftOn) {
            // Lock order: institutes → institute_staff → user_roles (inside revoke).
            $institute = Institute::query()->whereKey($staff->institute_id)->lockForUpdate()->firstOrFail();
            $staff = InstituteStaff::query()->whereKey($staff->getKey())->lockForUpdate()->firstOrFail();

            if ($staff->status !== 'active' || $staff->left_on !== null) {
                throw new DomainRuleException(DomainRuleException::NOT_ACTIVE);
            }

            if ($leftOn < $staff->joined_on->toDateString()) {
                throw new DomainRuleException(DomainRuleException::INVALID_DATE);
            }

            $user = User::query()->findOrFail($staff->user_id);

            $this->roles->revoke($user, Role::INSTITUTE_STAFF, $institute);

            $staff->forceFill(['status' => 'former', 'left_on' => $leftOn])->save();

            $this->audit->record(AuditAction::STAFF_ENDED, $staff, $institute, [
                'status' => 'active',
                'left_on' => null,
            ], [
                'user_uuid' => $user->uuid,
                'institute_uuid' => $institute->uuid,
                'position' => $staff->position,
                'status' => 'former',
                'joined_on' => $staff->joined_on->toDateString(),
                'left_on' => $leftOn,
            ]);
        });
    }

}
