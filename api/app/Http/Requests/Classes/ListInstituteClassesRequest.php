<?php

namespace App\Http\Requests\Classes;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Rules\LookupCode;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/institutes/{uuid}/classes?status=&subject_code=&grade_code=&teacher_uuid= (§7.2).
 */
class ListInstituteClassesRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('viewClasses', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([TuitionClass::STATUS_DRAFT, TuitionClass::STATUS_ACTIVE, TuitionClass::STATUS_ARCHIVED])],
            'subject_code' => ['sometimes', 'string', new LookupCode('subjects')],
            'grade_code' => ['sometimes', 'string', new LookupCode('grades')],
            'teacher_uuid' => ['sometimes', 'string', 'uuid'],
            ...$this->paginationRules(),
        ];
    }
}
