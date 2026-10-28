<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The contract §1.5 instant format: ISO 8601 UTC without fractional seconds, e.g. 2026-10-19T08:30:00Z.
 */
final class Instant
{
    public static function format(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
