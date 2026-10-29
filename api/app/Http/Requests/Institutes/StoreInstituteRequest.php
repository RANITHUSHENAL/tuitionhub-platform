<?php

namespace App\Http\Requests\Institutes;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/institutes (contract v0.5 §5.1). Owner, status and ID fields are rejected as
 * unknown fields. Eligibility and the district are checked by InstituteService.
 */
class StoreInstituteRequest extends FormRequest
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
        return InstituteFieldRules::rules(required: true);
    }
}
