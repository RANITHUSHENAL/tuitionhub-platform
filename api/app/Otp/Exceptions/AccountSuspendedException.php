<?php

namespace App\Otp\Exceptions;

use RuntimeException;

/**
 * A correct code for a suspended account: the challenge is consumed, nobody is logged in (D-019).
 * Rendered as 403.
 */
class AccountSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This account is suspended.');
    }
}
