<?php

namespace App\Http\Resources;

use App\Models\GuardianInvitation;
use App\Support\Instant;
use App\Support\PhoneMasker;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One invitation in a student's history (contract v0.10 §9.7, E2b; D-028). Never the
 * code or its hash, never the full phone, never an internal ID. `status` is the effective status
 * at the request's single `asOf` instant — the same instant the status filter used — so a filtered
 * page never shows a status other than the one asked for. Load `invitedBy` first.
 *
 * @mixin GuardianInvitation
 */
class GuardianInvitationHistoryResource extends JsonResource
{
    private ?CarbonImmutable $asOf = null;

    public function asOf(CarbonImmutable $instant): static
    {
        $this->asOf = $instant;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'relationship' => $this->relationship,
            'guardian_phone_masked' => PhoneMasker::mask($this->guardian_phone),
            'status' => $this->resource->effectiveStatus($this->asOf),
            'expires_at' => Instant::format($this->expires_at),
            'created_at' => $this->created_at === null ? null : Instant::format($this->created_at),
            'invited_by' => ['uuid' => $this->invitedBy->uuid, 'name' => $this->invitedBy->name],
        ];
    }
}
