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
    ];

    private const DENIED_FRAGMENTS = ['password', 'token', 'secret', 'hash'];

    private const ALLOWED = [
        AuditAction::INSTITUTE_CREATED => ['name', 'district', 'city', 'status'],
        AuditAction::ROLE_GRANTED => ['role', 'user_uuid', 'institute_uuid'],
        AuditAction::ROLE_REVOKED => ['role', 'user_uuid', 'institute_uuid'],
        AuditAction::STAFF_ADDED => ['user_uuid', 'institute_uuid', 'position', 'status', 'joined_on'],
        AuditAction::STAFF_ENDED => ['user_uuid', 'institute_uuid', 'position', 'status', 'joined_on', 'left_on'],
        AuditAction::TEACHER_ASSIGNMENT_ENDED => ['teacher_uuid', 'institute_uuid', 'status', 'joined_on', 'left_on'],
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
