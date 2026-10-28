<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A business rule was violated. The reason is a stable machine-readable code; HTTP mapping
 * is decided when the endpoints exist.
 */
class DomainRuleException extends RuntimeException
{
    public const UNKNOWN_ROLE = 'unknown_role';

    public const ROLE_SCOPE_MISMATCH = 'role_scope_mismatch';

    public const ROLE_ALREADY_GRANTED = 'role_already_granted';

    public const ROLE_NOT_GRANTED = 'role_not_granted';

    public const OWNER_ADMIN_REVOCATION_FORBIDDEN = 'owner_admin_revocation_forbidden';

    public const NOT_ELIGIBLE = 'not_eligible';

    public const UNKNOWN_DISTRICT = 'unknown_district';

    public const UNKNOWN_POSITION = 'unknown_position';

    public const ALREADY_ACTIVE = 'already_active';

    public const NOT_ACTIVE = 'not_active';

    public const INVALID_DATE = 'invalid_date';

    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }
}
