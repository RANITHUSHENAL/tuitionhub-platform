<?php

namespace App\Http\Resources;

use App\Models\TuitionClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimal `PublicClass` shape (contract v0.8 §7). No enrolment counts, capacity, status,
 * syllabus, teacher UUID, ratings, rank or boost indicators. The teacher is omitted entirely when
 * their profile's public_visibility is `hidden`. Load through PublicClassResource::query().
 *
 * @mixin TuitionClass
 */
class PublicClassResource extends JsonResource
{
    /**
     * Only publicly listed classes: active, opted in, at an active institute (§7.3, §7.5).
     *
     * @return Builder<TuitionClass>
     */
    public static function query(): Builder
    {
        return TuitionClass::query()
            ->publiclyListed()
            ->with(['institute', 'teacher', 'subject', 'grade', 'stream', 'examTarget', 'medium', 'feeVersions']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shape = [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'mode' => $this->mode,
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'subject' => ClassResource::lookup($this->subject),
            'grade' => ClassResource::lookup($this->grade),
            'stream' => ClassResource::lookup($this->stream),
            'exam_target' => ClassResource::lookup($this->examTarget),
            'medium' => ClassResource::lookup($this->medium),
            'institute' => ['name' => $this->institute->name, 'district' => $this->institute->district, 'city' => $this->institute->city],
        ];

        if ($this->teacher->public_visibility !== 'hidden') {
            $shape['teacher'] = ['display_name' => $this->teacher->display_name];
        }

        $shape['current_fee'] = ClassResource::fee($this->resource->currentFeeVersion());

        return $shape;
    }
}
