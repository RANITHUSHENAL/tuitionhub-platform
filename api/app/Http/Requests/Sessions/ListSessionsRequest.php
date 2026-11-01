<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\ClassSession;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/classes/{uuid}/sessions?from=&to=&status= (contract v0.9 §10.2).
 */
class ListSessionsRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var TuitionClass $class */
        $class = $this->route('class');

        return Gate::inspect('viewSessions', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'string', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['sometimes', 'string', Rule::in([ClassSession::STATUS_SCHEDULED, ClassSession::STATUS_CANCELLED])],
            ...$this->paginationRules(),
        ];
    }
}
