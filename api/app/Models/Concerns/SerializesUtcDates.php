<?php

namespace App\Models\Concerns;

use App\Support\Instant;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Instants are UTC end to end (D-005, contract §1.5).
 *
 * - Writing: a datetime-cast attribute given a DateTime in another zone is converted to UTC first.
 *   Without this, Eloquent stores the local wall-clock time as if it were UTC.
 * - Serializing: instants render as 2026-10-19T08:30:00Z, with no fractional seconds.
 *
 * Calendar dates use `immutable_date:Y-m-d` casts and are never converted, so they never shift a day.
 */
trait SerializesUtcDates
{
    public function setAttribute($key, $value)
    {
        if ($value instanceof DateTimeInterface && $this->hasCast($key, ['datetime', 'immutable_datetime'])) {
            $value = CarbonImmutable::instance($value)->utc();
        }

        return parent::setAttribute($key, $value);
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return Instant::format($date);
    }
}
