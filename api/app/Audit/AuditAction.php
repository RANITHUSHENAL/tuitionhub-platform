<?php

namespace App\Audit;

/**
 * Audit event names from contract v0.9 §13. Only events with an allowlist in AuditPayload can be recorded.
 */
final class AuditAction
{
    public const INSTITUTE_CREATED = 'institute.created';

    public const INSTITUTE_UPDATED = 'institute.updated';

    public const INSTITUTE_STATUS_CHANGED = 'institute.status_changed';

    public const USER_PROFILE_UPDATED = 'user.profile_updated';

    public const ROLE_GRANTED = 'role.granted';

    public const ROLE_REVOKED = 'role.revoked';

    public const STAFF_ADDED = 'staff.added';

    public const STAFF_ENDED = 'staff.ended';

    public const TEACHER_ASSIGNMENT_ENDED = 'teacher_assignment.ended';

    public const TEACHER_ASSIGNED = 'teacher.assigned';

    public const STUDENT_CREATED = 'student.created';

    public const GUARDIAN_INVITATION_CREATED = 'guardian_invitation.created';

    public const GUARDIAN_INVITATION_ACCEPTED = 'guardian_invitation.accepted';

    public const GUARDIAN_INVITATION_REVOKED = 'guardian_invitation.revoked';

    public const GUARDIAN_LINK_CREATED = 'guardian_link.created';

    public const GUARDIAN_LINK_REVOKED = 'guardian_link.revoked';

    public const CLASS_CREATED = 'class.created';

    public const CLASS_UPDATED = 'class.updated';

    public const FEE_VERSION_CREATED = 'fee.version_created';

    public const ENROLLMENT_CREATED = 'enrollment.created';

    public const ENROLLMENT_WITHDRAWN = 'enrollment.withdrawn';

    public const SESSION_CREATED = 'session.created';

    public const SESSION_CANCELLED = 'session.cancelled';

    public const ATTENDANCE_RECORDED = 'attendance.recorded';

    public const ATTENDANCE_CORRECTED = 'attendance.corrected';
}
