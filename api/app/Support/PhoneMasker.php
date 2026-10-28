<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Masks a Sri Lankan E.164 mobile for display and audit snapshots (contract v0.3 §9.1, §13):
 * +94771234567 becomes +9477*****67.
 */
final class PhoneMasker
{
    public static function mask(string $phone): string
    {
        if (preg_match('/^[+]947[0-9]{8}$/', $phone) !== 1) {
            throw new InvalidArgumentException('Only E.164 Sri Lankan mobile numbers can be masked.');
        }

        return substr($phone, 0, 5).'*****'.substr($phone, -2);
    }
}
