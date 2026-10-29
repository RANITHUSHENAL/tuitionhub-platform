<?php

namespace App\Http\Requests\Institutes;

use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\Institute;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/institutes (contract v0.5 §5.2): super_admin only, checked before validation.
 */
class ListInstitutesRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(): Response
    {
        return Gate::inspect('viewAny', Institute::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.Institute::STATUS_ACTIVE.','.Institute::STATUS_SUSPENDED],
            'district' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
            ...$this->paginationRules(),
        ];
    }
}
