<?php

namespace App\Rules;

use App\Support\Lookups;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A taxonomy code that exists in its seeded lookup table, matched exactly (contract §1.5). The
 * tables use a case-insensitive collation, so Laravel's `exists` rule would accept `g11` for `G11`;
 * codes are identifiers, so the spelling must match.
 */
class LookupCode implements ValidationRule
{
    public function __construct(private readonly string $table) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Lookups::id($this->table, $value) === null) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
