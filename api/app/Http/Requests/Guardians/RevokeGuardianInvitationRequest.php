<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\GuardianInvitation;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/guardian-invitations/{uuid}/revoke (contract v0.7 §9.3). No body: any field is a 422
 * (§1.6), checked only after authorization.
 */
class RevokeGuardianInvitationRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var GuardianInvitation $invitation */
        $invitation = $this->route('invitation');

        return Gate::inspect('revoke', $invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
