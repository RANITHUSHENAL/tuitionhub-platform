<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Models\User;
use App\Support\LocalClock;
use App\Support\Transactions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records attendance for a session (contract v0.9 §10.3; docs/SPRINT_SCHEMA.md G6.1; D-024,
 * D-027). One row per session and enrolment: a new mark is inserted, a changed mark is a
 * correction updated in place, an unchanged mark writes nothing (§1.6). marked_by_user_id is
 * always the caller. Manual method only.
 *
 * Lock order (D-022 Pattern I, D-024): institutes → classes → class_sessions, each by primary key;
 * class_enrollments and existing attendance are plain reads after the class lock (every writer of
 * them holds it), and attendance rows change by primary key. users only shared.
 */
class AttendanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{enrollment_uuid: string, status: string, method: string, notes?: string|null}>  $records  validated, no duplicate enrolments
     * @return list<Attendance> the session's rows for the submitted enrolments, in request order
     *
     * @throws AuthorizationException 403/404 from ClassSessionPolicy::markAttendance
     * @throws DomainRuleException INSTITUTE_SUSPENDED, CLASS_ARCHIVED, SESSION_CANCELLED, SESSION_IN_FUTURE
     * @throws ValidationException an enrolment outside the class, or not active on the session date
     */
    public function mark(User $actor, ClassSession $session, array $records): array
    {
        return Transactions::retrying(function () use ($actor, $session, $records) {
            $institute = Institute::query()
                ->whereKey(TuitionClass::query()->whereKey($session->class_id)->value('institute_id'))
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('markAttendance', [$session, $institute]);

            $class = TuitionClass::query()->whereKey($session->class_id)->lockForUpdate()->firstOrFail();
            $session = ClassSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $date = $session->session_date->format('Y-m-d');

            // D-024 Q46: an archived class takes no attendance.
            if ($class->status === TuitionClass::STATUS_ARCHIVED) {
                throw new DomainRuleException(DomainRuleException::CLASS_ARCHIVED);
            }

            if ($session->status === ClassSession::STATUS_CANCELLED) {
                throw new DomainRuleException(DomainRuleException::SESSION_CANCELLED);
            }

            // D-024 Q55: never for a session after today (Asia/Colombo).
            if ($date > LocalClock::today()) {
                throw new DomainRuleException(DomainRuleException::SESSION_IN_FUTURE);
            }

            $enrollments = ClassEnrollment::query()
                ->where('class_id', $class->getKey())
                ->whereIn('uuid', array_column($records, 'enrollment_uuid'))
                ->get()
                ->keyBy('uuid');

            $this->assertEligible($records, $enrollments->all(), $date);

            $existing = Attendance::query()
                ->where('session_id', $session->getKey())
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->get()
                ->keyBy('enrollment_id');

            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $result = [];

            foreach ($records as $record) {
                $enrollment = $enrollments[$record['enrollment_uuid']];
                $mark = ['status' => $record['status'], 'method' => $record['method']];

                // Omitted notes keep the stored value; an explicit null clears it (D-027).
                if (array_key_exists('notes', $record)) {
                    $mark['notes'] = $record['notes'];
                }

                $attendance = $existing->get($enrollment->getKey());

                if ($attendance === null) {
                    $attendance = $this->record($actor, $session, $class, $institute, $enrollment, $mark, $now);
                } else {
                    $this->correct($actor, $session, $institute, $enrollment, $attendance, $mark, $now);
                }

                $result[] = $attendance->setRelation('enrollment', $enrollment);
            }

            return $result;
        });
    }

    /**
     * Each enrolment must belong to the session's class and be active on the session date
     * (enrolled_on <= d < withdrawn_on, D-024 Q48). A later withdrawal does not stop corrections
     * for dates before it.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, ClassEnrollment>  $enrollments  keyed by UUID, this class only
     *
     * @throws ValidationException
     */
    private function assertEligible(array $records, array $enrollments, string $date): void
    {
        $errors = [];

        foreach ($records as $index => $record) {
            $enrollment = $enrollments[$record['enrollment_uuid']] ?? null;

            if ($enrollment === null) {
                $errors["records.{$index}.enrollment_uuid"] = 'The enrolment is not in this class.';
            } elseif (! $enrollment->isActiveOn($date)) {
                $errors["records.{$index}.enrollment_uuid"] = 'The enrolment is not active on the session date.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array{status: string, method: string, notes?: string|null}  $mark
     */
    private function record(
        User $actor,
        ClassSession $session,
        TuitionClass $class,
        Institute $institute,
        ClassEnrollment $enrollment,
        array $mark,
        CarbonImmutable $now,
    ): Attendance {
        $attendance = new Attendance;
        $attendance->forceFill([
            'session_id' => $session->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'class_id' => $class->getKey(),
            'status' => $mark['status'],
            'method' => $mark['method'],
            'notes' => $mark['notes'] ?? null,
            'marked_by_user_id' => $actor->getKey(),
            'marked_at' => $now,
        ]);
        $attendance->save();

        $this->audit->record(AuditAction::ATTENDANCE_RECORDED, $attendance, $institute, [], [
            'session_uuid' => $session->uuid,
            'enrollment_uuid' => $enrollment->uuid,
            'status' => $mark['status'],
            'method' => $mark['method'],
        ]);

        return $attendance;
    }

    /**
     * A changed mark is a correction (§10.3): updated in place by primary key, with a new marker and
     * time. Only status, method and (when submitted) notes are compared: if none of them changed,
     * the row is left alone, so marked_by and marked_at never change on their own. The audit row
     * has the old and new status and method, and only whether the notes changed.
     *
     * @param  array{status: string, method: string, notes?: string|null}  $mark
     */
    private function correct(
        User $actor,
        ClassSession $session,
        Institute $institute,
        ClassEnrollment $enrollment,
        Attendance $attendance,
        array $mark,
        CarbonImmutable $now,
    ): void {
        $before = ['status' => $attendance->status, 'method' => $attendance->method];
        $attendance->forceFill($mark);

        if (! $attendance->isDirty()) {
            return;
        }

        $notesChanged = $attendance->isDirty('notes');
        $attendance->forceFill(['marked_by_user_id' => $actor->getKey(), 'marked_at' => $now])->save();

        $this->audit->record(AuditAction::ATTENDANCE_CORRECTED, $attendance, $institute, $before, [
            'session_uuid' => $session->uuid,
            'enrollment_uuid' => $enrollment->uuid,
            'status' => $mark['status'],
            'method' => $mark['method'],
            'notes_changed' => $notesChanged,
        ]);
    }
}
