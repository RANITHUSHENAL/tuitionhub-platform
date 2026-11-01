<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/me/teacher-profile (contract v0.10 §6.5, E2b; D-028). The caller's own
 * profile only, so any authenticated, active caller may ask. No query fields (§1.6).
 */
class ShowMyTeacherProfileRequest extends FormRequest
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
        return [];
    }
}
