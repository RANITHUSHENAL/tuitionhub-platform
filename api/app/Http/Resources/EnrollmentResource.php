<?php

namespace App\Http\Resources;

use App\Models\ClassEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The enrolment shape (contract v0.8 §8.3–§8.5): the student's UUID and name only. Load the
 * `student` and `tuitionClass` relations first.
 *
 * @mixin ClassEnrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'enrolled_on' => $this->enrolled_on->format('Y-m-d'),
            'withdrawn_on' => $this->withdrawn_on?->format('Y-m-d'),
            'student' => ['uuid' => $this->student->uuid, 'full_name' => $this->student->full_name],
            'class' => ['uuid' => $this->tuitionClass->uuid, 'title' => $this->tuitionClass->title],
        ];
    }
}
