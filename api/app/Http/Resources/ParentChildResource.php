<?php

namespace App\Http\Resources;

use App\Models\StudentGuardian;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One child in GET /parents/me/children (contract v0.7 §9.5), built from an active link with
 * `student.grade` loaded. No internal IDs.
 *
 * active_enrollments is always [] until the enrolment tables exist (E2-1); no class is invented.
 *
 * @mixin StudentGuardian
 */
class ParentChildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $student = $this->student;

        return [
            'student' => [
                'uuid' => $student->uuid,
                'full_name' => $student->full_name,
                'grade' => ['code' => $student->grade->code, 'name' => $student->grade->name],
            ],
            'relationship' => $this->relationship,
            'linked_at' => Instant::format($this->linked_at),
            'active_enrollments' => [],
        ];
    }
}
