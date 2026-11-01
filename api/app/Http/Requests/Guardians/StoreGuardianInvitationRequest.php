<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\GuardianInvitation;
use App\Models\StudentProfile;
use App\Support\SriLankanMobile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/students/{uuid}/guardian-invitations (contract v0.7 §9.1). Authorization runs before
 * validation. The student, institute, inviter, status, expiry and code are server-owned.
 */
class StoreGuardianInvitationRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return Gate::inspect('inviteGuardian', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Local 0771234567 or E.164 +94771234567, stored as E.164 (E2-6).
            'guardian_phone' => ['required', 'string', 'regex:'.SriLankanMobile::E164_PATTERN],
            'relationship' => ['required', 'string', Rule::in(GuardianInvitation::RELATIONSHIPS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('guardian_phone'))) {
            $this->merge(['guardian_phone' => SriLankanMobile::normalize($this->input('guardian_phone'))]);
        }
    }
}
