<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Calendar dates are Asia/Colombo dates (contract §1.5); instants stay UTC.
 */
final class LocalClock
{
    public const TIMEZONE = 'Asia/Colombo';

    public static function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Accepts only a real calendar date written exactly as YYYY-MM-DD.
     *
     * @throws DomainRuleException INVALID_DATE
     */
    public static function date(string $value): string
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            $date = false;
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainRuleException(DomainRuleException::INVALID_DATE);
        }

        return $value;
    }
}
