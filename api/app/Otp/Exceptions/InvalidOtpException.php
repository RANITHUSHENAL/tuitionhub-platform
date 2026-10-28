<?php

namespace App\Otp\Exceptions;

use RuntimeException;

/**
 * Unknown, expired, consumed, mismatched or wrong code. One generic message, so the response never
 * reveals which check failed (contract §4.2). Rendered as 422.
 */
class InvalidOtpException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The code is invalid or has expired.');
    }
}
