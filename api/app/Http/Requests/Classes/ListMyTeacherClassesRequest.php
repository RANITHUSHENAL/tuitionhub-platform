<?php

namespace App\Http\Requests\Classes;

use App\Authorization\TenantAccess;
use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/me/teacher/classes?status= (contract v0.8 §6.4). A caller without a teacher profile
 * gets 403.
 */
class ListMyTeacherClassesRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(TenantAccess $access): Response
    {
        return $access->teacherProfileId($this->user()) === null
            ? Response::deny('You do not have a teacher profile.')
            : Response::allow();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([TuitionClass::STATUS_DRAFT, TuitionClass::STATUS_ACTIVE, TuitionClass::STATUS_ARCHIVED])],
            ...$this->paginationRules(),
        ];
    }
}
