<?php

namespace App\Guardians\Exceptions;

use RuntimeException;

/**
 * The single generic 422 for accepting an invitation (contract §9.2): unknown, malformed, expired,
 * used or revoked code, a phone mismatch, an unverified phone, or the inviter accepting their own
 * invitation (E2-4). One message for every case, so a response never tells which one applied.
 * Never chains another exception and never contains the code. Not reported (expected outcome).
 */
class InvalidInvitationCodeException extends RuntimeException
{
    public const MESSAGE = 'The invitation code is invalid or has expired.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
