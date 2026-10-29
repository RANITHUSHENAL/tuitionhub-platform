<?php

namespace App\Http\Resources;

use App\Models\Institute;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Institute` shape (contract v0.5 §5). An explicit allowlist: internal IDs and the owner are
 * never exposed.
 *
 * @mixin Institute
 */
class InstituteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'district' => $this->district,
            'city' => $this->city,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'business_reg_no' => $this->business_reg_no,
            'created_at' => $this->created_at === null ? null : Instant::format($this->created_at),
            'updated_at' => $this->updated_at === null ? null : Instant::format($this->updated_at),
        ];
    }
}
