<?php

namespace App\Audit;

/**
 * Audit event names from contract v0.3 §13. Only events with an allowlist in AuditPayload can be recorded.
 */
final class AuditAction
{
    public const INSTITUTE_CREATED = 'institute.created';

    public const ROLE_GRANTED = 'role.granted';

    public const ROLE_REVOKED = 'role.revoked';

    public const STAFF_ADDED = 'staff.added';

    public const STAFF_ENDED = 'staff.ended';

    public const TEACHER_ASSIGNMENT_ENDED = 'teacher_assignment.ended';
}
