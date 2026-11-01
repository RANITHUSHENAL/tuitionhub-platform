<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/guardian-invitations/accept (contract v0.7 §9.2). Any authenticated, active user;
 * whether the code is theirs is decided by GuardianInvitationService. The code format is checked
 * there too, so every bad code gets the same generic 422.
 */
class AcceptGuardianInvitationRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}
