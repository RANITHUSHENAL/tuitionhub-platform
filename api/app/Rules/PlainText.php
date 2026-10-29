<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Free text such as institute names, cities and descriptions: valid UTF-8 with no control
 * characters and no invisible format characters, except ZWNJ/ZWJ (needed by Sinhala and Tamil).
 * Multi-line text may also contain line feeds, carriage returns and tabs.
 */
class PlainText implements ValidationRule
{
    public function __construct(private readonly bool $multiline = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $withoutAllowed = $this->multiline ? str_replace(["\r", "\n", "\t"], '', $value) : $value;

        if (! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\p{Cc}\p{Co}\p{Cn}]|(?![\x{200C}\x{200D}])\p{Cf}/u', $withoutAllowed) === 1) {
            $fail('The :attribute contains characters that are not allowed.');
        }
    }
}
