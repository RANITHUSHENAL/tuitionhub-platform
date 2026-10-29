<?php

namespace App\Http\Resources;

use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Teacher` shape (contract v0.6 §6). An explicit allowlist: the user, internal IDs and the
 * not-yet-editable visibility fields are never exposed.
 *
 * @mixin TeacherProfile
 */
class TeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'display_name' => $this->display_name,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'years_experience' => (int) $this->years_experience,
        ];
    }
}
