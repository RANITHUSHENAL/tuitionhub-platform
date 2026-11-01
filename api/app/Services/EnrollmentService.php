<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Models\TuitionClass;
use App\Models\User;
use App\Support\LocalClock;
use App\Support\Transactions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Enrols students in classes and withdraws them (contract v0.8 §8.3, §8.5;
 * docs/SPRINT_SCHEMA.md G5.3; D-024, D-026).
 *
 * Lock order (D-022 Pattern I, D-024): institutes → classes → class_enrollments, each by primary
 * key; student_profiles and users only shared (foreign-key checks, audit rows). Active counts,
 * duplicates and existing attendance are plain reads after the class lock, which every writer of
 * those rows holds. Enrolments are never deleted or reactivated.
 */
class EnrollmentService
{
    private const UNIQUE_ACTIVE_KEY = 'class_enrollments_active_unique';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string|null  $enrolledOn  YYYY-MM-DD; today (Asia/Colombo) when omitted
     *
     * @throws AuthorizationException 403/404 from ClassPolicy::enroll
     * @throws ModelNotFoundException the student is not managed by the class's institute (404)
     * @throws DomainRuleException INSTITUTE_SUSPENDED, NOT_ACTIVE (class), ALREADY_ACTIVE, CLASS_FULL
     * @throws ValidationException enrolled_on outside the class dates
     */
    public function enroll(User $actor, TuitionClass $class, string $studentUuid, ?string $enrolledOn = null): ClassEnrollment
    {
        return Transactions::retrying(function () use ($actor, $class, $studentUuid, $enrolledOn) {
            $institute = Institute::query()->whereKey($class->institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('enroll', [$class, $institute]);

            // Validated only after authorization (§1.4).
            $enrolledOn = LocalClock::date($enrolledOn ?? LocalClock::today());

            $class = TuitionClass::query()->whereKey($class->getKey())->lockForUpdate()->firstOrFail();

            if ($class->status !== TuitionClass::STATUS_ACTIVE) {
                throw new DomainRuleException(DomainRuleException::NOT_ACTIVE);
            }

            // Scoped to the class's institute before anything is revealed (contract §1.1, §8.3).
            $student = StudentProfile::query()
                ->where('uuid', $studentUuid)
                ->where('managing_institute_id', $institute->getKey())
                ->first() ?? throw (new ModelNotFoundException)->setModel(StudentProfile::class);

            // D-024: may be in the future, but within the class's dates.
            if ($enrolledOn < $class->starts_on->format('Y-m-d')
                || ($class->ends_on !== null && $enrolledOn > $class->ends_on->format('Y-m-d'))) {
                throw ValidationException::withMessages(['enrolled_on' => 'The enrolled on date must be within the class dates.']);
            }

            $active = ClassEnrollment::query()->active()->where('class_id', $class->getKey());

            if ((clone $active)->where('student_id', $student->getKey())->exists()) {
                throw new DomainRuleException(DomainRuleException::ALREADY_ACTIVE);
            }

            // A future enrolment is active from creation, so it takes a place at once (D-024).
            if ((clone $active)->count() >= $class->max_students) {
                throw new DomainRuleException(DomainRuleException::CLASS_FULL);
            }

            $enrollment = new ClassEnrollment;
            $enrollment->forceFill([
                'class_id' => $class->getKey(),
                'student_id' => $student->getKey(),
                'institute_id' => $institute->getKey(),
                'status' => ClassEnrollment::STATUS_ACTIVE,
                'enrolled_on' => $enrolledOn,
                'withdrawn_on' => null,
            ]);

            try {
                $enrollment->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), self::UNIQUE_ACTIVE_KEY)) {
                    // Not chained (D-022): database error text is never carried into business errors.
                    throw new DomainRuleException(DomainRuleException::ALREADY_ACTIVE);
                }

                throw $exception;
            }

            $this->audit->record(AuditAction::ENROLLMENT_CREATED, $enrollment, $institute, [], [
                'enrollment_uuid' => $enrollment->uuid,
                'class_uuid' => $class->uuid,
                'student_uuid' => $student->uuid,
                'status' => ClassEnrollment::STATUS_ACTIVE,
                'enrolled_on' => $enrolledOn,
            ]);

            return $enrollment->setRelation('tuitionClass', $class)->setRelation('student', $student);
        });
    }

    /**
     * Withdraws an active enrolment (§8.5). The row is kept; re-enrolling creates a new one.
     *
     * - A withdrawal date before enrolled_on is stored as enrolled_on: a zero-length enrolment
     *   that was never active (D-024).
     * - Attendance for a session on or after the withdrawal date would fall outside the enrolment,
     *   so the withdrawal is refused (409) and history is never invalidated (D-024).
     *
     * @param  string  $withdrawnOn  YYYY-MM-DD, today (Asia/Colombo) or earlier
     *
     * @throws AuthorizationException 403/404 from ClassEnrollmentPolicy::withdraw
     * @throws DomainRuleException INSTITUTE_SUSPENDED, NOT_ACTIVE, ATTENDANCE_RECORDED
     * @throws ValidationException withdrawn_on in the future
     */
    public function withdraw(User $actor, ClassEnrollment $enrollment, string $withdrawnOn): ClassEnrollment
    {
        return Transactions::retrying(function () use ($actor, $enrollment, $withdrawnOn) {
            $institute = Institute::query()->whereKey($enrollment->institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('withdraw', [$enrollment, $institute]);

            // Validated only after authorization, so a hidden enrolment is a 404, never a 422 (§1.4).
            $requested = LocalClock::date($withdrawnOn);

            if ($requested > LocalClock::today()) {
                throw ValidationException::withMessages(['withdrawn_on' => 'The withdrawn on date must not be in the future.']);
            }

            $class = TuitionClass::query()->whereKey($enrollment->class_id)->lockForUpdate()->firstOrFail();
            $enrollment = ClassEnrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();

            if ($enrollment->status !== ClassEnrollment::STATUS_ACTIVE) {
                throw new DomainRuleException(DomainRuleException::NOT_ACTIVE);
            }

            $enrolledOn = $enrollment->enrolled_on->format('Y-m-d');
            $effective = max($requested, $enrolledOn);

            $attendanceAfter = Attendance::query()
                ->where('enrollment_id', $enrollment->getKey())
                ->whereHas('session', fn (Builder $session) => $session->where('session_date', '>=', $effective))
                ->exists();

            if ($attendanceAfter) {
                throw new DomainRuleException(DomainRuleException::ATTENDANCE_RECORDED);
            }

            $enrollment->forceFill(['status' => ClassEnrollment::STATUS_WITHDRAWN, 'withdrawn_on' => $effective])->save();

            $student = StudentProfile::query()->findOrFail($enrollment->student_id);

            $this->audit->record(AuditAction::ENROLLMENT_WITHDRAWN, $enrollment, $institute, [
                'status' => ClassEnrollment::STATUS_ACTIVE,
            ], [
                'enrollment_uuid' => $enrollment->uuid,
                'class_uuid' => $class->uuid,
                'student_uuid' => $student->uuid,
                'status' => ClassEnrollment::STATUS_WITHDRAWN,
                'enrolled_on' => $enrolledOn,
                'withdrawn_on' => $effective,
            ]);

            return $enrollment->setRelation('tuitionClass', $class)->setRelation('student', $student);
        });
    }
}
