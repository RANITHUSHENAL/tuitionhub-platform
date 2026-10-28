<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * A business rule was violated. The reason is a stable machine-readable code (contract v0.4 §1.3).
 *
 * HTTP mapping: state conflicts are 409 with a `reason` field; NOT_ELIGIBLE is 403; invalid values
 * are 422 with Laravel's `errors` shape. Reasons not listed fall back to 422.
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

    /** 409 state conflicts, rendered with their reason code. */
    private const CONFLICTS = [
        self::ROLE_ALREADY_GRANTED => 'This role is already granted.',
        self::ROLE_NOT_GRANTED => 'This role is not granted.',
        self::OWNER_ADMIN_REVOCATION_FORBIDDEN => 'The institute owner always keeps the institute admin role.',
        self::ALREADY_ACTIVE => 'This record is already active.',
        self::NOT_ACTIVE => 'This record is not active.',
    ];

    /** 422 invalid values: reason => [field, message]. */
    private const INVALID = [
        self::UNKNOWN_ROLE => ['role', 'The selected role is invalid.'],
        self::ROLE_SCOPE_MISMATCH => ['role', 'The role does not match the requested scope.'],
        self::UNKNOWN_DISTRICT => ['district', 'The selected district is invalid.'],
        self::UNKNOWN_POSITION => ['position', 'The selected position is invalid.'],
        self::INVALID_DATE => ['date', 'The date must be a valid date in YYYY-MM-DD format.'],
    ];

    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }

    public function toResponse(): JsonResponse
    {
        if (array_key_exists($this->reason, self::CONFLICTS)) {
            return response()->json(['message' => self::CONFLICTS[$this->reason], 'reason' => $this->reason], 409);
        }

        if ($this->reason === self::NOT_ELIGIBLE) {
            return response()->json(['message' => 'You are not eligible to perform this action.', 'reason' => $this->reason], 403);
        }

        [$field, $message] = self::INVALID[$this->reason] ?? ['request', 'The request is invalid.'];

        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
