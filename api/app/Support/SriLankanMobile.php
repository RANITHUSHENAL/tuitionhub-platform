<?php

namespace App\Support;

/**
 * Sri Lankan mobile numbers (contract §1.5; E2-6). Guardian phones may be entered in the local
 * format 0771234567 or in E.164 +94771234567; both are stored as E.164. Anything else is returned
 * unchanged, so validation rejects it.
 */
final class SriLankanMobile
{
    public const E164_PATTERN = '/^[+]947[0-9]{8}$/D';

    public static function normalize(#[\SensitiveParameter] string $phone): string
    {
        if (preg_match('/^07[0-9]{8}$/D', $phone) === 1) {
            return '+94'.substr($phone, 1);
        }

        return $phone;
    }
}
