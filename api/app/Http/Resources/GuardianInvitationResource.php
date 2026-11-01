<?php

namespace App\Http\Resources;

use App\Models\GuardianInvitation;
use App\Support\Instant;
use App\Support\PhoneMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation as shown to the institute (contract v0.7 §9.1), without the code: the controller
 * adds it to the create response only. The phone is always masked and the status is the effective
 * one (lazy expiry). Load the `student` relation first.
 *
 * @mixin GuardianInvitation
 */
class GuardianInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'student' => ['uuid' => $this->student->uuid, 'full_name' => $this->student->full_name],
            'guardian_phone_masked' => PhoneMasker::mask($this->guardian_phone),
            'relationship' => $this->relationship,
            'status' => $this->resource->effectiveStatus(),
            'expires_at' => Instant::format($this->expires_at),
        ];
    }
}
