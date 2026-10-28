<?php

namespace App\Otp\Exceptions;

use RuntimeException;

/**
 * The OTP service must not run: driver not allowed here (e.g. log/fake in production), unknown
 * driver, or missing HMAC key. Rendered as 503 (contract §4.1).
 */
class OtpUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sign-in codes are temporarily unavailable.');
    }
}
