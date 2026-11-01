<?php

namespace App\Http\Resources;

use App\Models\ClassEnrollment;
use App\Models\StudentGuardian;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One child in GET /parents/me/children (contract v0.9 §9.5), built from an active link with
 * `student.grade` and the student's active enrolments (with class and institute) loaded. No
 * internal IDs.
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
            'active_enrollments' => $student->enrollments->map(fn (ClassEnrollment $enrollment) => [
                'uuid' => $enrollment->uuid,
                'class' => ['uuid' => $enrollment->tuitionClass->uuid, 'title' => $enrollment->tuitionClass->title],
                'institute' => ['name' => $enrollment->tuitionClass->institute->name],
            ])->values()->all(),
        ];
    }
}
