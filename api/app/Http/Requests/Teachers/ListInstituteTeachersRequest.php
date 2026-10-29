<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\Institute;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/institutes/{uuid}/teachers?status=&page=&per_page= (contract v0.6 §6.3).
 */
class ListInstituteTeachersRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('viewTeachers', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:active,former'],
            ...$this->paginationRules(),
        ];
    }
}
