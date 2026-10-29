<?php

namespace App\Http\Resources;

use App\Models\StudentProfile;
use App\Support\Instant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Student` shape, institute view (contract v0.6 §8). Load the model through
 * StudentResource::query() so the lookups and the active-guardian count are present.
 *
 * @mixin StudentProfile
 */
class StudentResource extends JsonResource
{
    /**
     * Students with what this resource needs: lookups eager-loaded, and only **active** guardian
     * links counted (revoked links are history, contract §3).
     *
     * @return Builder<StudentProfile>
     */
    public static function query(): Builder
    {
        return StudentProfile::query()
            ->with(['grade', 'medium', 'examTarget'])
            ->withCount(['guardianLinks as active_guardian_count' => fn (Builder $links) => $links->active()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'grade' => ['code' => $this->grade->code, 'name' => $this->grade->name],
            'exam_target' => $this->examTarget === null
                ? null
                : ['code' => $this->examTarget->code, 'name' => $this->examTarget->name],
            'medium' => ['code' => $this->medium->code, 'name' => $this->medium->name],
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'has_user_account' => $this->user_id !== null,
            'active_guardian_count' => (int) $this->active_guardian_count,
            'created_at' => $this->created_at === null ? null : Instant::format($this->created_at),
        ];
    }
}
