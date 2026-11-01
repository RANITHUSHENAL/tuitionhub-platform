<?php

namespace App\Http\Requests\Classes;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Rules\LookupCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/public/classes?subject_code=&grade_code=&district=&q= (contract v0.8 §7.5). Public.
 * An unknown district is a 422 on `district` (canonicalised by App\Support\District).
 */
class ListPublicClassesRequest extends FormRequest
{
    use ValidatesPagination;

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
            'subject_code' => ['sometimes', 'string', new LookupCode('subjects')],
            'grade_code' => ['sometimes', 'string', new LookupCode('grades')],
            'district' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
            ...$this->paginationRules(),
        ];
    }
}
