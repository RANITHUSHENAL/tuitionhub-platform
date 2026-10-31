<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A database transaction kept losing a concurrency race (deadlock retries exhausted, or a lock
 * wait timed out). Rendered as 503 with Retry-After: 1 (contract §1.4; D-022). Nothing was
 * committed, so the client may safely repeat the request.
 */
class ServiceBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The service is busy. Please try again.');
    }
}
