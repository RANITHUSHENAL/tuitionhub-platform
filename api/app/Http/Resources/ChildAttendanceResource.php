<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of a guardian's view of their child's attendance (contract v0.9 §10.5). No notes, no
 * marker and no other student's data. Load `session` and `tuitionClass` first.
 *
 * @mixin Attendance
 */
class ChildAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session' => [
                'uuid' => $this->session->uuid,
                'session_date' => $this->session->session_date->format('Y-m-d'),
                'start_time' => SessionResource::time($this->session->start_time),
                'end_time' => SessionResource::time($this->session->end_time),
            ],
            'class' => ['uuid' => $this->tuitionClass->uuid, 'title' => $this->tuitionClass->title],
            'status' => $this->status,
            'marked_at' => Instant::format($this->marked_at),
        ];
    }
}
