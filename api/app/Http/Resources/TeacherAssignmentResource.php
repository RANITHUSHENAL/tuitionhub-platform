<?php

namespace App\Http\Resources;

use App\Models\Institute;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A teacher assignment (contract v0.6 §6.2, §6.3). The list item (§6.3) is
 * { teacher, status, joined_on, left_on }; the create response (§6.2) adds institute_uuid.
 * The `teacher` relation must be eager-loaded.
 *
 * @mixin TeacherAssignment
 */
class TeacherAssignmentResource extends JsonResource
{
    private ?Institute $institute = null;

    public function withInstitute(Institute $institute): static
    {
        $this->institute = $institute;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'teacher' => TeacherResource::make($this->teacher)->resolve($request),
            ...($this->institute === null ? [] : ['institute_uuid' => $this->institute->uuid]),
            'status' => $this->status,
            'joined_on' => $this->joined_on->format('Y-m-d'),
            'left_on' => $this->left_on?->format('Y-m-d'),
        ];
    }
}
