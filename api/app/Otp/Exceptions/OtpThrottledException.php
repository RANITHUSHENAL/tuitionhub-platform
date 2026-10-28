<?php

namespace App\Otp\Exceptions;

use RuntimeException;

/**
 * Resend cooldown or exhausted attempts. Rendered as 429 with Retry-After (contract §4.1, §4.2).
 */
class OtpThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many attempts. Please wait before trying again.');
    }
}
