<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Write requests accept only the fields their rules name (contract v0.5 §1.6; FD-5).
 *
 * Any other top-level key, in the body or the query string, is a 422 on that key. This covers the
 * contract rules that reject owner, status, ID and personal-document fields, without a list that
 * could fall out of date.
 */
trait RejectsUnknownFields
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = array_map(
                fn (string $rule) => explode('.', $rule)[0],
                array_keys($this->rules()),
            );

            foreach (array_keys($this->all()) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $validator->errors()->add((string) $key, "The {$key} field is not allowed.");
                }
            }
        });
    }
}
