<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\TeacherAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\LocalClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assigns teachers to institutes (contract v0.6 §6.2) and ends assignments (docs/SPRINT_SCHEMA.md
 * G3.3). A teacher's access is derived from the active assignment alone: this service never writes
 * user_roles (contract §3, D-011). Ended assignments grant nothing on the next check.
 */
class TeacherAssignmentService
{
    private const UNIQUE_ACTIVE_KEY = 'teacher_institute_active_unique';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Creates an active assignment. The institute row is locked and the actor is authorized again
     * against it, so a suspension or role removal committed after the request's first check is
     * respected. A second active assignment of the same teacher is refused by the unique key.
     *
     * joined_on must be today or earlier (Asia/Colombo). An active assignment grants access at
     * once, so a future start date would grant access before it begins, and end() could never
     * close it today (left_on must not precede joined_on). Backdated assignments are valid.
     *
     * @throws AuthorizationException 403/404 from InstitutePolicy::assignTeacher
     * @throws DomainRuleException INSTITUTE_SUSPENDED, ALREADY_ACTIVE, INVALID_DATE
     * @throws ValidationException joined_on is in the future
     */
    public function assign(User $actor, Institute $institute, TeacherProfile $teacher, string $joinedOn): TeacherAssignment
    {
        $joinedOn = LocalClock::date($joinedOn);

        if ($joinedOn > LocalClock::today()) {
            throw ValidationException::withMessages(['joined_on' => 'The joined on date must not be in the future.']);
        }

        return DB::transaction(function () use ($actor, $institute, $teacher, $joinedOn) {
            // Lock order: institutes → teacher_institute.
            $institute = Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('assignTeacher', $institute);

            $assignment = new TeacherAssignment;
            $assignment->forceFill([
                'teacher_id' => $teacher->getKey(),
                'institute_id' => $institute->getKey(),
                'joined_on' => $joinedOn,
                'left_on' => null,
                'status' => 'active',
            ]);

            try {
                $assignment->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), self::UNIQUE_ACTIVE_KEY)) {
                    throw new DomainRuleException(DomainRuleException::ALREADY_ACTIVE, $exception);
                }

                throw $exception;
            }

            $this->audit->record(AuditAction::TEACHER_ASSIGNED, $assignment, $institute, [], [
                'teacher_uuid' => $teacher->uuid,
                'institute_uuid' => $institute->uuid,
                'status' => 'active',
                'joined_on' => $joinedOn,
            ]);

            return $assignment;
        });
    }

    /**
     * @throws DomainRuleException NOT_ACTIVE, INVALID_DATE
     */
    public function end(TeacherAssignment $assignment, ?string $leftOn = null): void
    {
        $leftOn = LocalClock::date($leftOn ?? LocalClock::today());

        DB::transaction(function () use ($assignment, $leftOn) {
            // Lock order: institutes → teacher_institute.
            $institute = Institute::query()->whereKey($assignment->institute_id)->lockForUpdate()->firstOrFail();
            $assignment = TeacherAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if ($assignment->status !== 'active' || $assignment->left_on !== null) {
                throw new DomainRuleException(DomainRuleException::NOT_ACTIVE);
            }

            if ($leftOn < $assignment->joined_on->toDateString()) {
                throw new DomainRuleException(DomainRuleException::INVALID_DATE);
            }

            $assignment->forceFill(['status' => 'former', 'left_on' => $leftOn])->save();

            $this->audit->record(AuditAction::TEACHER_ASSIGNMENT_ENDED, $assignment, $institute, [
                'status' => 'active',
                'left_on' => null,
            ], [
                'teacher_uuid' => $assignment->teacher()->value('uuid'),
                'institute_uuid' => $institute->uuid,
                'status' => 'former',
                'joined_on' => $assignment->joined_on->toDateString(),
                'left_on' => $leftOn,
            ]);
        });
    }
}
