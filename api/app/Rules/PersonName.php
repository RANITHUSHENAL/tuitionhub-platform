<?php

namespace App\Rules;

use App\Support\PersonName as PersonNameSupport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a name already passed through App\Support\PersonName::normalize() (the request's
 * prepareForValidation does that). The maximum is 100 for users and teachers and 150 for students;
 * longer input is rejected, never truncated.
 */
class PersonName implements ValidationRule
{
    public function __construct(private readonly int $maxLength = PersonNameSupport::MAX_LENGTH) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        match (PersonNameSupport::violation($value, $this->maxLength)) {
            null => null,
            PersonNameSupport::ERROR_LENGTH => $fail(sprintf(
                'The :attribute must be between %d and %d characters.',
                PersonNameSupport::MIN_LENGTH,
                $this->maxLength,
            )),
            default => $fail('The :attribute may contain only letters, spaces, full stops, apostrophes and hyphens.'),
        };
    }
}
