<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;

/**
 * The 25 districts of Sri Lanka in their canonical stored spelling (docs/SPRINT_SCHEMA.md G3.1).
 * The database CHECK is case-insensitive, so the server must store this exact spelling.
 */
final class District
{
    public const ALL = [
        'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo',
        'Galle', 'Gampaha', 'Hambantota', 'Jaffna', 'Kalutara',
        'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala', 'Mannar',
        'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya',
        'Polonnaruwa', 'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya',
    ];

    private const ALIASES = [
        'moneragala' => 'Monaragala',
    ];

    public static function canonical(string $value): string
    {
        $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));

        foreach (self::ALL as $district) {
            if (mb_strtolower($district) === $key) {
                return $district;
            }
        }

        return self::ALIASES[$key] ?? throw new DomainRuleException(DomainRuleException::UNKNOWN_DISTRICT);
    }
}
