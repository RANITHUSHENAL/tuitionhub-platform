<?php

namespace App\Http\Resources;

use App\Models\ClassFeeVersion;
use App\Models\TuitionClass;
use App\Support\Instant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `Class` shape, authorised views (contract v0.8 §7). Load the model through
 * ClassResource::query() so the relations and the active-enrolment count are present.
 *
 * @mixin TuitionClass
 */
class ClassResource extends JsonResource
{
    /**
     * @return Builder<TuitionClass>
     */
    public static function query(): Builder
    {
        return TuitionClass::query()
            ->with(['institute', 'teacher', 'subject', 'grade', 'stream', 'examTarget', 'medium', 'syllabus', 'feeVersions'])
            ->withCount(['enrollments as active_enrollment_count' => fn (Builder $enrollments) => $enrollments->active()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'mode' => $this->mode,
            'max_students' => (int) $this->max_students,
            'active_enrollment_count' => (int) $this->active_enrollment_count,
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'),
            'public_listing' => (bool) $this->public_listing,
            'institute' => [
                'uuid' => $this->institute->uuid,
                'name' => $this->institute->name,
                'district' => $this->institute->district,
                'city' => $this->institute->city,
            ],
            'teacher' => ['uuid' => $this->teacher->uuid, 'display_name' => $this->teacher->display_name],
            'subject' => self::lookup($this->subject),
            'grade' => self::lookup($this->grade),
            'stream' => self::lookup($this->stream),
            'exam_target' => self::lookup($this->examTarget),
            'medium' => self::lookup($this->medium),
            'syllabus' => self::lookup($this->syllabus),
            'current_fee' => self::fee($this->resource->currentFeeVersion()),
            'created_at' => $this->created_at === null ? null : Instant::format($this->created_at),
            'updated_at' => $this->updated_at === null ? null : Instant::format($this->updated_at),
        ];
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public static function lookup(?Model $lookup): ?array
    {
        return $lookup === null ? null : ['code' => $lookup->getAttribute('code'), 'name' => $lookup->getAttribute('name')];
    }

    /**
     * @return array{amount: string, currency: string, billing_cycle: string, effective_from: string}|null
     */
    public static function fee(?ClassFeeVersion $version): ?array
    {
        return $version === null ? null : [
            'amount' => $version->amount,
            'currency' => $version->currency,
            'billing_cycle' => $version->billing_cycle,
            'effective_from' => $version->effective_from->format('Y-m-d'),
        ];
    }
}
