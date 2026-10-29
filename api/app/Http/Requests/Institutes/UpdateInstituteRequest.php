<?php

namespace App\Http\Requests\Institutes;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\Institute;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * PATCH /api/v1/institutes/{uuid} (contract v0.5 §5.5). Authorization runs before validation, so a
 * hidden institute is a 404 and a forbidden caller a 403 whatever the body contains.
 * InstituteService authorizes again against the locked row.
 */
class UpdateInstituteRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        $response = Gate::inspect('update', $institute);

        if ($response->allowed() && $this->exists('status')) {
            return Gate::inspect('changeStatus', $institute);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...InstituteFieldRules::rules(required: false),
            'status' => ['sometimes', 'string', 'in:'.Institute::STATUS_ACTIVE.','.Institute::STATUS_SUSPENDED],
        ];
    }
}
