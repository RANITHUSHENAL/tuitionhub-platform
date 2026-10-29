<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Rules\PersonName;
use App\Support\PersonName as PersonNameSupport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/v1/me (contract v0.5 §4.5). Only `name`; it is normalized before validation.
 */
class UpdateMeRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new PersonName],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => PersonNameSupport::normalize($this->input('name'))]);
        }
    }
}
