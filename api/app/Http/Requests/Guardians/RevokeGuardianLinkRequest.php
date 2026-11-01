<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\StudentProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * DELETE /api/v1/students/{uuid}/guardian-links/{guardianUuid} (contract v0.7 §9.4). No body or
 * query fields. Authorization runs first, so the guardian is looked up only for an allowed caller.
 */
class RevokeGuardianLinkRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return Gate::inspect('revokeGuardianLink', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
