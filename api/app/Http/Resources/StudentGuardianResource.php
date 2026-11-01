<?php

namespace App\Http\Resources;

use App\Models\StudentGuardian;
use App\Support\Instant;
use App\Support\PhoneMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One active guardian link, as the student's institute sees it (contract v0.10 §9.6, E2b;
 * D-028). An explicit allowlist: `guardian.uuid` is the guardian's **user** UUID, the one §9.4
 * takes; the phone is masked with PhoneMasker; no internal IDs, full phone, or invitation data.
 * Load `guardian.user` first.
 *
 * @mixin StudentGuardian
 */
class StudentGuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->guardian->user;

        return [
            'guardian' => ['uuid' => $user->uuid, 'name' => $user->name],
            'relationship' => $this->relationship,
            'is_primary' => (bool) $this->is_primary,
            'linked_at' => Instant::format($this->linked_at),
            'guardian_phone_masked' => PhoneMasker::mask($user->phone),
        ];
    }
}
