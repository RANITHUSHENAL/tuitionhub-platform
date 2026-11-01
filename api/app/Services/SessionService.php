<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Models\User;
use App\Support\LocalClock;
use App\Support\Transactions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates and cancels class sessions (contract v0.9 §10.1, §10.6; docs/SPRINT_SCHEMA.md G5.4;
 * D-024, D-027). Times are Asia/Colombo wall-clock times in whole minutes; a cancelled session is
 * kept as history and frees its slot.
 *
 * Lock order (D-022 Pattern I, D-024): institutes → classes → class_sessions, each by primary key;
 * users only shared. The duplicate-slot and attendance checks are plain reads after the class
 * lock, which every writer of sessions and attendance holds.
 */
class SessionService
{
    private const UNIQUE_SLOT_KEY = 'class_sessions_slot_unique';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{session_date: string, start_time: string, end_time: string}  $attributes  validated, times HH:MM
     *
     * @throws AuthorizationException 403/404 from ClassPolicy::manageSessions
     * @throws DomainRuleException INSTITUTE_SUSPENDED, NOT_ACTIVE (class), SESSION_SLOT_TAKEN
     * @throws ValidationException date outside the class dates, end not after start
     */
    public function create(User $actor, TuitionClass $class, array $attributes): ClassSession
    {
        return Transactions::retrying(function () use ($actor, $class, $attributes) {
            $institute = Institute::query()->whereKey($class->institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('manageSessions', [$class, $institute]);

            $class = TuitionClass::query()->whereKey($class->getKey())->lockForUpdate()->firstOrFail();

            // D-024 Q47: only an active class takes sessions.
            if ($class->status !== TuitionClass::STATUS_ACTIVE) {
                throw new DomainRuleException(DomainRuleException::NOT_ACTIVE);
            }

            $date = LocalClock::date((string) $attributes['session_date']);
            $start = $this->minutes((string) $attributes['start_time']);
            $end = $this->minutes((string) $attributes['end_time']);

            if ($date < $class->starts_on->format('Y-m-d') || ($class->ends_on !== null && $date > $class->ends_on->format('Y-m-d'))) {
                throw ValidationException::withMessages(['session_date' => 'The session date must be within the class dates.']);
            }

            if ($end <= $start) {
                throw ValidationException::withMessages(['end_time' => 'The end time must be after the start time.']);
            }

            $taken = ClassSession::query()
                ->where('class_id', $class->getKey())
                ->where('session_date', $date)
                ->where('start_time', $start)
                ->where('status', '<>', ClassSession::STATUS_CANCELLED)
                ->exists();

            if ($taken) {
                throw new DomainRuleException(DomainRuleException::SESSION_SLOT_TAKEN);
            }

            $session = new ClassSession;
            $session->forceFill([
                'class_id' => $class->getKey(),
                'session_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'status' => ClassSession::STATUS_SCHEDULED,
                'created_by_user_id' => $actor->getKey(),
            ]);

            try {
                $session->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), self::UNIQUE_SLOT_KEY)) {
                    // Not chained (D-022).
                    throw new DomainRuleException(DomainRuleException::SESSION_SLOT_TAKEN);
                }

                throw $exception;
            }

            $this->audit->record(AuditAction::SESSION_CREATED, $session, $institute, [], $this->snapshot($session, $class));

            return $session->setRelation('tuitionClass', $class);
        });
    }

    /**
     * scheduled → cancelled, once (§10.6). The row is kept and frees its slot. A session that
     * already has attendance cannot be cancelled (D-024 Q50), and an archived class is final.
     *
     * @throws AuthorizationException 403/404 from ClassSessionPolicy::cancel
     * @throws DomainRuleException INSTITUTE_SUSPENDED, CLASS_ARCHIVED, SESSION_CANCELLED, SESSION_HAS_ATTENDANCE
     */
    public function cancel(User $actor, ClassSession $session): ClassSession
    {
        return Transactions::retrying(function () use ($actor, $session) {
            $institute = Institute::query()
                ->whereKey(TuitionClass::query()->whereKey($session->class_id)->value('institute_id'))
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('cancel', [$session, $institute]);

            $class = TuitionClass::query()->whereKey($session->class_id)->lockForUpdate()->firstOrFail();
            $session = ClassSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($class->status === TuitionClass::STATUS_ARCHIVED) {
                throw new DomainRuleException(DomainRuleException::CLASS_ARCHIVED);
            }

            if ($session->status === ClassSession::STATUS_CANCELLED) {
                throw new DomainRuleException(DomainRuleException::SESSION_CANCELLED);
            }

            if (Attendance::query()->where('session_id', $session->getKey())->exists()) {
                throw new DomainRuleException(DomainRuleException::SESSION_HAS_ATTENDANCE);
            }

            $session->forceFill(['status' => ClassSession::STATUS_CANCELLED])->save();

            $this->audit->record(
                AuditAction::SESSION_CANCELLED,
                $session,
                $institute,
                ['status' => ClassSession::STATUS_SCHEDULED],
                $this->snapshot($session, $class),
            );

            return $session->setRelation('tuitionClass', $class);
        });
    }

    /**
     * HH:MM (validated) as the stored TIME value.
     */
    private function minutes(string $time): string
    {
        return substr($time, 0, 5).':00';
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(ClassSession $session, TuitionClass $class): array
    {
        return [
            'session_uuid' => $session->uuid,
            'class_uuid' => $class->uuid,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time' => substr((string) $session->start_time, 0, 5),
            'end_time' => substr((string) $session->end_time, 0, 5),
            'status' => $session->status,
        ];
    }
}
