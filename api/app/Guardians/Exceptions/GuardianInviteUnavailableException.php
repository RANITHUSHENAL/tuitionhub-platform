<?php

namespace App\Guardians\Exceptions;

use RuntimeException;

/**
 * GUARDIAN_INVITE_HMAC_KEY is missing or too short: invitation create and accept fail closed with
 * 503 (contract §9.1). Reported, because it is a configuration fault; the message never names the key.
 */
class GuardianInviteUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Guardian invitations are not available.');
    }
}
