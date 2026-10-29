<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Lookups;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates institute-managed student records (contract v0.6 §8.1; C6; docs/SPRINT_SCHEMA.md G4.1).
 *
 * The managing institute comes from the authorized route, the creator from the session, and the
 * student has no user account (user_id NULL). No NIC, ID documents or photos exist in the schema.
 * Fictional data only.
 */
class StudentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The institute row is locked and the actor is authorized again against it, so a suspension or
     * role removal committed after the request's first check is respected. The audit row never
     * contains the student's name or date of birth.
     *
     * @param  array<string, mixed>  $attributes  validated request data (codes, not IDs)
     *
     * @throws AuthorizationException 403/404 from InstitutePolicy::createStudent
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     * @throws ValidationException a lookup code no longer exists
     */
    public function create(User $actor, Institute $institute, array $attributes): StudentProfile
    {
        return DB::transaction(function () use ($actor, $institute, $attributes) {
            // Lock order: institutes first (no users row is locked here).
            $institute = Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('createStudent', $institute);

            $gradeCode = (string) $attributes['grade_code'];
            $mediumCode = (string) $attributes['medium_code'];
            $examTargetCode = $attributes['exam_target_code'] ?? null;

            $student = new StudentProfile;
            $student->forceFill([
                'managing_institute_id' => $institute->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'user_id' => null,
                'full_name' => $attributes['full_name'],
                'grade_id' => $this->lookupId('grades', $gradeCode, 'grade_code'),
                'medium_id' => $this->lookupId('mediums', $mediumCode, 'medium_code'),
                'exam_target_id' => $examTargetCode === null
                    ? null
                    : $this->lookupId('exam_targets', (string) $examTargetCode, 'exam_target_code'),
                'date_of_birth' => $attributes['date_of_birth'] ?? null,
            ]);
            $student->save();

            $this->audit->record(AuditAction::STUDENT_CREATED, $student, $institute, [], [
                'student_uuid' => $student->uuid,
                'institute_uuid' => $institute->uuid,
                'grade_code' => $gradeCode,
                'medium_code' => $mediumCode,
                'exam_target_code' => $examTargetCode,
            ]);

            return $student;
        });
    }

    /**
     * @throws ValidationException
     */
    private function lookupId(string $table, string $code, string $field): int
    {
        return Lookups::id($table, $code)
            ?? throw ValidationException::withMessages([$field => "The selected {$field} is invalid."]);
    }
}
