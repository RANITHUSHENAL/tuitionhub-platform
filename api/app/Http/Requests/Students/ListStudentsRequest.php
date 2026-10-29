<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\Institute;
use App\Rules\LookupCode;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/institutes/{uuid}/students?q=&grade_code=&page=&per_page= (contract v0.6 §8.2).
 */
class ListStudentsRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('viewStudents', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:100'],
            'grade_code' => ['sometimes', 'string', new LookupCode('grades')],
            ...$this->paginationRules(),
        ];
    }
}
