<?php

namespace App\Audit;

use LogicException;

/**
 * Builds audit snapshots (docs/SPRINT_SCHEMA.md R2.0 rule 3; contract v0.3 §13).
 *
 * The denylist runs first and throws on any forbidden key, because passing one is a programmer
 * error. The per-action allowlist then drops every key it does not name.
 */
final class AuditPayload
{
    /**
     * Never allowed in a snapshot: codes and hashes, credentials, student personal data, full phones.
     */
    private const DENIED_KEYS = [
        'code', 'code_hash', 'otp', 'password', 'remember_token',
        'full_name', 'date_of_birth',
        'phone', 'guardian_phone', 'contact_phone',
        'email', 'contact_email',
    ];

    private const DENIED_FRAGMENTS = ['password', 'token', 'secret', 'hash'];

    private const ALLOWED = [
        AuditAction::INSTITUTE_CREATED => ['name', 'district', 'city', 'status'],
        // Contact details, the description and the registration number are recorded only as
        // "changed" flags, never as values (D-020).
        AuditAction::INSTITUTE_UPDATED => [
            'name', 'district', 'city',
            'description_changed', 'contact_phone_changed', 'contact_email_changed', 'business_reg_no_changed',
        ],
        AuditAction::INSTITUTE_STATUS_CHANGED => ['status'],
        // The name itself is never recorded (FD-2).
        AuditAction::USER_PROFILE_UPDATED => ['user_uuid', 'field', 'name_was_set', 'onboarding_completed'],
        AuditAction::ROLE_GRANTED => ['role', 'user_uuid', 'institute_uuid'],
        AuditAction::ROLE_REVOKED => ['role', 'user_uuid', 'institute_uuid'],
        AuditAction::STAFF_ADDED => ['user_uuid', 'institute_uuid', 'position', 'status', 'joined_on'],
        AuditAction::STAFF_ENDED => ['user_uuid', 'institute_uuid', 'position', 'status', 'joined_on', 'left_on'],
        AuditAction::TEACHER_ASSIGNMENT_ENDED => ['teacher_uuid', 'institute_uuid', 'status', 'joined_on', 'left_on'],
        AuditAction::TEACHER_ASSIGNED => ['teacher_uuid', 'institute_uuid', 'status', 'joined_on'],
        // Never the student's name or date of birth (both are on the denylist above).
        AuditAction::STUDENT_CREATED => ['student_uuid', 'institute_uuid', 'grade_code', 'medium_code', 'exam_target_code'],
        // Guardian events (D-023): UUIDs, statuses, the masked phone and internal link IDs only.
        // Never the code, its hash, the full phone or the student's name.
        AuditAction::GUARDIAN_INVITATION_CREATED => [
            'invitation_uuid', 'student_uuid', 'institute_uuid', 'relationship', 'guardian_phone_masked', 'status', 'expires_at',
        ],
        AuditAction::GUARDIAN_INVITATION_ACCEPTED => ['invitation_uuid', 'student_uuid', 'status'],
        AuditAction::GUARDIAN_INVITATION_REVOKED => ['invitation_uuid', 'student_uuid', 'status'],
        // primary_link_id: internal student_guardians IDs, recorded only when the primary changes (Q37).
        AuditAction::GUARDIAN_LINK_CREATED => ['student_uuid', 'guardian_uuid', 'relationship', 'is_primary', 'primary_link_id'],
        AuditAction::GUARDIAN_LINK_REVOKED => ['student_uuid', 'guardian_uuid', 'relationship', 'is_primary', 'primary_link_id'],
        // Classes and enrolments (D-026). The description is recorded only as a "changed" flag;
        // students appear by UUID only, never by name.
        AuditAction::CLASS_CREATED => self::CLASS_FIELDS,
        AuditAction::CLASS_UPDATED => [...self::CLASS_FIELDS, 'description_changed'],
        AuditAction::FEE_VERSION_CREATED => ['class_uuid', 'amount', 'currency', 'billing_cycle', 'effective_from', 'previous_effective_to'],
        AuditAction::ENROLLMENT_CREATED => ['enrollment_uuid', 'class_uuid', 'student_uuid', 'status', 'enrolled_on'],
        AuditAction::ENROLLMENT_WITHDRAWN => ['enrollment_uuid', 'class_uuid', 'student_uuid', 'status', 'enrolled_on', 'withdrawn_on'],
    ];

    private const CLASS_FIELDS = [
        'class_uuid', 'institute_uuid', 'teacher_uuid', 'title', 'status', 'mode', 'max_students', 'starts_on', 'ends_on',
        'public_listing', 'subject_code', 'grade_code', 'stream_code', 'exam_target_code', 'medium_code', 'syllabus_code',
    ];

    /**
     * @param  array<string, scalar|null>  $values
     * @return array<string, scalar|null>|null
     */
    public static function filter(string $action, array $values): ?array
    {
        if (! array_key_exists($action, self::ALLOWED)) {
            throw new LogicException("No audit allowlist is defined for [{$action}].");
        }

        foreach ($values as $key => $value) {
            if (self::isDenied((string) $key)) {
                throw new LogicException("Audit key [{$key}] is forbidden.");
            }

            if (! is_scalar($value) && $value !== null) {
                throw new LogicException("Audit value for [{$key}] must be a scalar.");
            }
        }

        $allowed = array_intersect_key($values, array_flip(self::ALLOWED[$action]));

        return $allowed === [] ? null : $allowed;
    }

    private static function isDenied(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::DENIED_KEYS, true)) {
            return true;
        }

        foreach (self::DENIED_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
