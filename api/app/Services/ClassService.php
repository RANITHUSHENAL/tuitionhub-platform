<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\ClassEnrollment;
use App\Models\ClassFeeVersion;
use App\Models\ClassSession;
use App\Models\Institute;
use App\Models\TeacherAssignment;
use App\Models\TeacherProfile;
use App\Models\TuitionClass;
use App\Models\User;
use App\Support\Lookups;
use App\Support\Transactions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates classes and their fee versions (contract v0.8 §7.1, §7.4;
 * docs/SPRINT_SCHEMA.md G5.1–G5.2; D-024, D-026).
 *
 * Lock order (D-022 Pattern I, D-024): institutes → classes → class_fee_versions, each by primary
 * key; users only shared (foreign keys, audit rows). Sets of rows (the teacher's active
 * assignment, active enrolments, the open fee version) are plain reads after the institute lock,
 * which every writer of those rows holds.
 */
class ClassService
{
    /** Request field => [column, lookup table]. stream_code is the only nullable one. */
    private const LOOKUPS = [
        'subject_code' => ['subject_id', 'subjects'],
        'grade_code' => ['grade_id', 'grades'],
        'stream_code' => ['stream_id', 'streams'],
        'exam_target_code' => ['exam_target_id', 'exam_targets'],
        'medium_code' => ['medium_id', 'mediums'],
        'syllabus_code' => ['syllabus_id', 'syllabi'],
    ];

    private const FIELDS = ['title', 'description', 'mode', 'max_students', 'starts_on', 'ends_on', 'public_listing'];

    /** D-024 Q46: draft → active → archived, or draft → archived. archived is final. */
    private const TRANSITIONS = [
        TuitionClass::STATUS_DRAFT => [TuitionClass::STATUS_ACTIVE, TuitionClass::STATUS_ARCHIVED],
        TuitionClass::STATUS_ACTIVE => [TuitionClass::STATUS_ARCHIVED],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Creates the class and its first fee version in one transaction (§7.1).
     *
     * @param  array<string, mixed>  $attributes  validated request data (codes and UUIDs, not IDs)
     *
     * @throws AuthorizationException 403/404 from InstitutePolicy::createClass
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     * @throws ValidationException teacher without an active assignment, unknown code, bad dates
     */
    public function create(User $actor, Institute $institute, array $attributes): TuitionClass
    {
        return Transactions::retrying(function () use ($actor, $institute, $attributes) {
            $institute = Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('createClass', $institute);

            $teacher = $this->assignedTeacher((string) $attributes['teacher_uuid'], $institute);
            $this->assertDateOrder((string) $attributes['starts_on'], $attributes['ends_on'] ?? null);

            $class = new TuitionClass;
            $class->forceFill([
                'institute_id' => $institute->getKey(),
                'teacher_id' => $teacher->getKey(),
                'stream_id' => null,
                ...$this->lookupIds($attributes),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'mode' => $attributes['mode'],
                'max_students' => (int) $attributes['max_students'],
                'starts_on' => $attributes['starts_on'],
                'ends_on' => $attributes['ends_on'] ?? null,
                'status' => $attributes['status'] ?? TuitionClass::STATUS_DRAFT,
                'public_listing' => (bool) ($attributes['public_listing'] ?? false),
            ]);
            $class->save();

            $this->audit->record(AuditAction::CLASS_CREATED, $class, $institute, [], $this->snapshot($class, $institute));
            $this->createFeeVersion($actor, $class, $institute, $attributes['fee']);

            return $class;
        });
    }

    /**
     * Updates the class (§7.4). Omitted fields are unchanged; an update that changes nothing writes
     * nothing (§1.6). A `fee` always creates a new version and closes the open one the day before.
     *
     * @param  array<string, mixed>  $attributes  validated request data
     *
     * @throws AuthorizationException 403/404 from ClassPolicy::update
     * @throws DomainRuleException INSTITUTE_SUSPENDED, CLASS_ARCHIVED, INVALID_STATUS_TRANSITION,
     *                             MAX_STUDENTS_BELOW_ACTIVE
     * @throws ValidationException teacher, codes, dates or fee date invalid
     */
    public function update(User $actor, TuitionClass $class, array $attributes): TuitionClass
    {
        return Transactions::retrying(function () use ($actor, $class, $attributes) {
            $institute = Institute::query()->whereKey($class->institute_id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('update', [$class, $institute]);

            $class = TuitionClass::query()->whereKey($class->getKey())->lockForUpdate()->firstOrFail();

            if ($class->status === TuitionClass::STATUS_ARCHIVED) {
                throw new DomainRuleException(DomainRuleException::CLASS_ARCHIVED);
            }

            $before = $this->snapshot($class, $institute);
            $changes = Arr::only($attributes, self::FIELDS);

            if (array_key_exists('teacher_uuid', $attributes)) {
                $changes['teacher_id'] = $this->assignedTeacher((string) $attributes['teacher_uuid'], $institute)->getKey();
            }

            if (array_key_exists('status', $attributes)) {
                $this->assertTransition($class->status, (string) $attributes['status']);
                $changes['status'] = $attributes['status'];
            }

            $class->forceFill([...$changes, ...$this->lookupIds($attributes, partial: true)]);

            $this->assertDateOrder($class->starts_on->format('Y-m-d'), $class->ends_on?->format('Y-m-d'));

            if ($class->isDirty(['starts_on', 'ends_on'])) {
                $this->assertActiveEnrollmentsWithinDates($class);
                $this->assertSessionsWithinDates($class);
            }

            if ($class->isDirty('max_students') && $class->max_students < $this->activeEnrollmentCount($class)) {
                throw new DomainRuleException(DomainRuleException::MAX_STUDENTS_BELOW_ACTIVE);
            }

            if ($class->isDirty()) {
                $descriptionChanged = $class->isDirty('description');
                $class->save();
                $this->auditChanges($class, $institute, $before, $descriptionChanged);
            }

            if (array_key_exists('fee', $attributes)) {
                $this->createFeeVersion($actor, $class, $institute, $attributes['fee']);
            }

            return $class;
        });
    }

    /**
     * Appends a fee version (contract §7.4; G5.2). Under the class lock: the open version is found
     * with a plain read, the new start must be after the open version's start, the open version is
     * closed the day before (by primary key) and the new one is inserted open.
     *
     * @param  array<string, mixed>  $fee
     *
     * @throws ValidationException fee.effective_from not after the open version's start
     */
    private function createFeeVersion(User $actor, TuitionClass $class, Institute $institute, array $fee): ClassFeeVersion
    {
        $from = (string) $fee['effective_from'];
        $open = ClassFeeVersion::query()->where('class_id', $class->getKey())->whereNull('effective_to')->first();
        $previousTo = null;

        if ($open !== null) {
            if ($from <= $open->effective_from->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'fee.effective_from' => 'The fee must take effect after the current fee\'s effective from date.',
                ]);
            }

            $previousTo = CarbonImmutable::createFromFormat('!Y-m-d', $from)->subDay()->format('Y-m-d');
            $open->forceFill(['effective_to' => $previousTo])->save();
        }

        $version = new ClassFeeVersion;
        $version->forceFill([
            'class_id' => $class->getKey(),
            'amount' => $fee['amount'],
            'currency' => 'LKR',
            'billing_cycle' => $fee['billing_cycle'],
            'effective_from' => $from,
            'effective_to' => null,
            'created_by_user_id' => $actor->getKey(),
        ]);
        $version->save();

        $this->audit->record(AuditAction::FEE_VERSION_CREATED, $version, $institute, [], [
            'class_uuid' => $class->uuid,
            'amount' => (string) $fee['amount'],
            'currency' => 'LKR',
            'billing_cycle' => (string) $fee['billing_cycle'],
            'effective_from' => $from,
            'previous_effective_to' => $previousTo,
        ]);

        return $version;
    }

    /**
     * The teacher, who must have an active assignment at this institute (§7.1). A plain read after
     * the institute lock: every writer of teacher_institute holds that lock.
     *
     * @throws ValidationException
     */
    private function assignedTeacher(string $teacherUuid, Institute $institute): TeacherProfile
    {
        $teacher = TeacherProfile::query()->where('uuid', $teacherUuid)->first();

        $assigned = $teacher !== null && TeacherAssignment::query()->active()
            ->where('teacher_id', $teacher->getKey())
            ->where('institute_id', $institute->getKey())
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages(['teacher_uuid' => 'The selected teacher has no active assignment at this institute.']);
        }

        return $teacher;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     *
     * @throws ValidationException
     */
    private function lookupIds(array $attributes, bool $partial = false): array
    {
        $ids = [];

        foreach (self::LOOKUPS as $field => [$column, $table]) {
            if (! array_key_exists($field, $attributes)) {
                if (! $partial && $field !== 'stream_code') {
                    throw ValidationException::withMessages([$field => "The {$field} field is required."]);
                }

                continue;
            }

            $code = $attributes[$field];

            if ($code === null && $field === 'stream_code') {
                $ids[$column] = null;

                continue;
            }

            $ids[$column] = Lookups::id($table, (string) $code)
                ?? throw ValidationException::withMessages([$field => "The selected {$field} is invalid."]);
        }

        return $ids;
    }

    /**
     * @throws ValidationException
     */
    private function assertDateOrder(string $startsOn, ?string $endsOn): void
    {
        if ($endsOn !== null && $endsOn < $startsOn) {
            throw ValidationException::withMessages(['ends_on' => 'The ends on date must be on or after the starts on date.']);
        }
    }

    /**
     * D-024: every active enrolment must start within the class dates.
     *
     * @throws ValidationException
     */
    private function assertActiveEnrollmentsWithinDates(TuitionClass $class): void
    {
        $active = ClassEnrollment::query()->active()->where('class_id', $class->getKey());

        if ((clone $active)->where('enrolled_on', '<', $class->starts_on->format('Y-m-d'))->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'An active enrolment starts before this date.']);
        }

        if ($class->ends_on !== null && (clone $active)->where('enrolled_on', '>', $class->ends_on->format('Y-m-d'))->exists()) {
            throw ValidationException::withMessages(['ends_on' => 'An active enrolment starts after this date.']);
        }
    }

    /**
     * D-026 carry-forward (E3b): every non-cancelled session must stay within the class dates.
     *
     * @throws ValidationException
     */
    private function assertSessionsWithinDates(TuitionClass $class): void
    {
        $sessions = ClassSession::query()
            ->where('class_id', $class->getKey())
            ->where('status', '<>', ClassSession::STATUS_CANCELLED);

        if ((clone $sessions)->where('session_date', '<', $class->starts_on->format('Y-m-d'))->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'A session is scheduled before this date.']);
        }

        if ($class->ends_on !== null && (clone $sessions)->where('session_date', '>', $class->ends_on->format('Y-m-d'))->exists()) {
            throw ValidationException::withMessages(['ends_on' => 'A session is scheduled after this date.']);
        }
    }

    /**
     * @throws DomainRuleException INVALID_STATUS_TRANSITION
     */
    private function assertTransition(string $from, string $to): void
    {
        if ($from !== $to && ! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainRuleException(DomainRuleException::INVALID_STATUS_TRANSITION);
        }
    }

    private function activeEnrollmentCount(TuitionClass $class): int
    {
        return ClassEnrollment::query()->active()->where('class_id', $class->getKey())->count();
    }

    /**
     * @param  array<string, scalar|null>  $before
     */
    private function auditChanges(TuitionClass $class, Institute $institute, array $before, bool $descriptionChanged): void
    {
        $after = $this->snapshot($class, $institute);
        $old = [];
        $new = ['class_uuid' => $class->uuid];

        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $old[$field] = $before[$field];
                $new[$field] = $value;
            }
        }

        if ($descriptionChanged) {
            $new['description_changed'] = true;
        }

        $this->audit->record(AuditAction::CLASS_UPDATED, $class, $institute, $old, $new);
    }

    /**
     * The audited view of a class: UUIDs, codes, dates and scalars only; never the description.
     *
     * @return array<string, scalar|null>
     */
    private function snapshot(TuitionClass $class, Institute $institute): array
    {
        $codes = [];

        foreach (self::LOOKUPS as $field => [$column, $table]) {
            $id = $class->getAttribute($column);
            $codes[$field] = $id === null ? null : DB::table($table)->where('id', $id)->value('code');
        }

        return [
            'class_uuid' => $class->uuid,
            'institute_uuid' => $institute->uuid,
            'teacher_uuid' => TeacherProfile::query()->whereKey($class->teacher_id)->value('uuid'),
            'title' => $class->title,
            'status' => $class->status,
            'mode' => $class->mode,
            'max_students' => (int) $class->max_students,
            'starts_on' => $class->starts_on->format('Y-m-d'),
            'ends_on' => $class->ends_on?->format('Y-m-d'),
            'public_listing' => (bool) $class->public_listing,
            ...$codes,
        ];
    }
}
