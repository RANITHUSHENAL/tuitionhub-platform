<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/SPRINT_SCHEMA.md G3.3 (table teacher_institute). teacher_id references teacher_profiles.
 */
#[Table('teacher_institute')]
#[Hidden(['id', 'teacher_id', 'institute_id', 'active_flag'])]
class TeacherAssignment extends Model
{
    use GuardsGeneratedColumns, HasPublicUuid, SerializesUtcDates;

    protected function casts(): array
    {
        return [
            'joined_on' => 'immutable_date:Y-m-d',
            'left_on' => 'immutable_date:Y-m-d',
        ];
    }

    protected function generatedColumns(): array
    {
        return ['active_flag'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active')->whereNull('left_on');
    }

    /**
     * @return BelongsTo<TeacherProfile, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Institute, $this>
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
