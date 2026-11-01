<?php

namespace App\Http\Resources;

use App\Models\ClassSession;
use App\Support\Instant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Session` shape (contract v0.9 §10): Asia/Colombo date and HH:MM times. Load the
 * `tuitionClass` relation first.
 *
 * @mixin ClassSession
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'class_uuid' => $this->tuitionClass->uuid,
            'session_date' => $this->session_date->format('Y-m-d'),
            'start_time' => self::time($this->start_time),
            'end_time' => self::time($this->end_time),
            'status' => $this->status,
            'created_at' => $this->created_at === null ? null : Instant::format($this->created_at),
        ];
    }

    public static function time(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
