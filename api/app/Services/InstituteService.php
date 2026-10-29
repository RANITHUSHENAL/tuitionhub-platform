<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Support\District;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creates and updates institutes, with their audit rows, atomically
 * (contract v0.5 §5.1, §5.5; D-011; D-020; docs/SPRINT_SCHEMA.md G3.1).
 */
class InstituteService
{
    private const ATTRIBUTES = ['name', 'description', 'district', 'city', 'contact_phone', 'contact_email', 'business_reg_no'];

    /** Recorded by value in institute.updated. */
    private const AUDITED_VALUES = ['name', 'district', 'city'];

    /** Recorded only as "<field>_changed": true in institute.updated, never by value. */
    private const AUDITED_FLAGS = ['description', 'contact_phone', 'contact_email', 'business_reg_no'];

    public function __construct(
        private readonly RoleAssignmentService $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  validated request data; owner and status are never taken from it
     *
     * @throws DomainRuleException NOT_ELIGIBLE, UNKNOWN_DISTRICT
     */
    public function create(User $owner, array $attributes): Institute
    {
        return DB::transaction(function () use ($owner, $attributes) {
            // Lock order: users first.
            $owner = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();

            if ($owner->status !== 'active' || $owner->phone_verified_at === null || $owner->name === null) {
                throw new DomainRuleException(DomainRuleException::NOT_ELIGIBLE);
            }

            $institute = new Institute;
            $institute->fill(Arr::only($attributes, self::ATTRIBUTES));
            $institute->forceFill([
                'district' => District::canonical((string) ($attributes['district'] ?? '')),
                'owner_user_id' => $owner->getKey(),
                'status' => Institute::STATUS_ACTIVE,
            ]);
            $institute->save();

            $this->roles->grant($owner, Role::INSTITUTE_ADMIN, $institute);

            $this->audit->record(
                AuditAction::INSTITUTE_CREATED,
                $institute,
                $institute,
                [],
                $institute->only(['name', 'district', 'city', 'status']),
            );

            return $institute;
        });
    }

    /**
     * Applies a validated PATCH (contract §5.5). The institute row is locked and the caller is
     * authorized again against the locked row, so a suspension, a role revocation or a concurrent
     * update committed after the request's first check is respected. Nothing changed → no write and
     * no audit row. Owner and ID fields are never accepted (the request rejects them).
     *
     * @param  array<string, mixed>  $attributes  validated request data
     *
     * @throws AuthorizationException 403/404 from InstitutePolicy
     * @throws DomainRuleException INSTITUTE_SUSPENDED, UNKNOWN_DISTRICT
     */
    public function update(User $actor, Institute $institute, array $attributes): Institute
    {
        return DB::transaction(function () use ($actor, $institute, $attributes) {
            // Lock order: users → institutes. Only the institute row is locked here.
            $institute = Institute::query()->whereKey($institute->getKey())->lockForUpdate()->firstOrFail();

            $gate = Gate::forUser($actor);
            $gate->authorize('update', $institute);

            if (array_key_exists('status', $attributes)) {
                $gate->authorize('changeStatus', $institute);
            }

            $changes = Arr::only($attributes, self::ATTRIBUTES);

            if (array_key_exists('district', $changes)) {
                $changes['district'] = District::canonical((string) $changes['district']);
            }

            $before = $institute->only([...self::ATTRIBUTES, 'status']);

            $institute->fill($changes);

            if (array_key_exists('status', $attributes)) {
                $institute->forceFill(['status' => $attributes['status']]);
            }

            $dirty = array_keys($institute->getDirty());

            if ($dirty === []) {
                return $institute;
            }

            $institute->save();

            $this->auditFieldChanges($institute, $before, $dirty);

            if (in_array('status', $dirty, true)) {
                $this->audit->record(
                    AuditAction::INSTITUTE_STATUS_CHANGED,
                    $institute,
                    $institute,
                    ['status' => $before['status']],
                    ['status' => $institute->status],
                );
            }

            return $institute;
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>  $dirty
     */
    private function auditFieldChanges(Institute $institute, array $before, array $dirty): void
    {
        $old = [];
        $new = [];

        foreach (self::AUDITED_VALUES as $field) {
            if (in_array($field, $dirty, true)) {
                $old[$field] = $before[$field];
                $new[$field] = $institute->getAttribute($field);
            }
        }

        foreach (self::AUDITED_FLAGS as $field) {
            if (in_array($field, $dirty, true)) {
                $new[$field.'_changed'] = true;
            }
        }

        if ($new === []) {
            return;
        }

        $this->audit->record(AuditAction::INSTITUTE_UPDATED, $institute, $institute, $old, $new);
    }
}
