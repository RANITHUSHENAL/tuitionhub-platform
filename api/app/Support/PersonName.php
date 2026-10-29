<?php

namespace App\Support;

use Normalizer;

/**
 * Person names in Sinhala, Tamil and Latin script (contract v0.5 §4.5; FD-1, FD-10).
 *
 * normalize() is applied before validation: NFC, every run of space separators (including
 * no-break and ideographic spaces) becomes one ASCII space, and the ends are trimmed. Control
 * characters are deliberately NOT turned into spaces, so a name containing one is rejected.
 *
 * Allowed: letters (\p{L}), combining marks (\p{M}, the Sinhala and Tamil vowel signs), ASCII space,
 * full stop, apostrophes (' and ’) and hyphen. ZWNJ (U+200C) and ZWJ (U+200D) are allowed only
 * between letters or marks, because Sinhala conjuncts need ZWJ (ශ්‍රී). Everything else is refused,
 * including digits, symbols, emoji, other format characters (bidi overrides U+202A–U+202E and
 * U+2066–U+2069, BOM, soft hyphen), private-use and unassigned code points, and invalid UTF-8.
 */
final class PersonName
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public const ERROR_CHARACTERS = 'characters';

    public const ERROR_LENGTH = 'length';

    private const ALLOWED = '/^\p{L}[\p{L}\p{M}\x{200C}\x{200D} .\'\x{2019}\-]*$/u';

    private const LAST_CHARACTER = '/[\p{L}\p{M}.]$/u';

    private const LONE_JOINER = '/(?<![\p{L}\p{M}])[\x{200C}\x{200D}]|[\x{200C}\x{200D}](?![\p{L}\p{M}])/u';

    public static function normalize(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            // Left untouched so validation rejects it.
            return $value;
        }

        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

        if ($normalized === false) {
            return $value;
        }

        return trim((string) preg_replace('/\p{Zs}+/u', ' ', $normalized), ' ');
    }

    /**
     * The reason a normalized name is refused, or null when it is valid.
     */
    public static function violation(string $name): ?string
    {
        if (! mb_check_encoding($name, 'UTF-8')) {
            return self::ERROR_CHARACTERS;
        }

        $length = mb_strlen($name, 'UTF-8');

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return self::ERROR_LENGTH;
        }

        if (preg_match(self::ALLOWED, $name) !== 1
            || preg_match(self::LAST_CHARACTER, $name) !== 1
            || preg_match(self::LONE_JOINER, $name) === 1) {
            return self::ERROR_CHARACTERS;
        }

        return null;
    }
}
