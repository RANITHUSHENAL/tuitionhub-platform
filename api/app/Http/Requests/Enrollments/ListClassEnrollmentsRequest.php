<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\ClassEnrollment;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/classes/{uuid}/enrollments?status= (contract v0.8 §8.4).
 */
class ListClassEnrollmentsRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var TuitionClass $class */
        $class = $this->route('class');

        return Gate::inspect('viewEnrollments', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([ClassEnrollment::STATUS_ACTIVE, ClassEnrollment::STATUS_WITHDRAWN])],
            ...$this->paginationRules(),
        ];
    }
}
