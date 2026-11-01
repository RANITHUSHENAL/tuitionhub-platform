<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the §10.3 response (contract v0.9). Load `enrollment` and `markedBy` first.
 *
 * @mixin Attendance
 */
class AttendanceMarkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enrollment_uuid' => $this->enrollment->uuid,
            'status' => $this->status,
            'method' => $this->method,
            'notes' => $this->notes,
            'marked_at' => Instant::format($this->marked_at),
            'marked_by' => ['uuid' => $this->markedBy->uuid, 'name' => $this->markedBy->name],
        ];
    }
}
