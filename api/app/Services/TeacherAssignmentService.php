<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\TeacherAssignment;
use App\Support\LocalClock;
use Illuminate\Support\Facades\DB;

/**
 * Ends teacher assignments (docs/SPRINT_SCHEMA.md G3.3). Assigning (contract §6.2) arrives with the
 * teacher endpoints. Ended assignments grant nothing on the next check (contract §3).
 */
class TeacherAssignmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

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
