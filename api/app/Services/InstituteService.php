<?php

namespace App\Services;

use App\Audit\AuditAction;
use App\Audit\AuditLogger;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Support\District;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates an institute, grants its owner institute_admin and audits both, atomically
 * (contract v0.3 §5.1; D-011; docs/SPRINT_SCHEMA.md G3.1).
 */
class InstituteService
{
    private const ATTRIBUTES = ['name', 'description', 'district', 'city', 'contact_phone', 'contact_email', 'business_reg_no'];

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
}
