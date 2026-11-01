<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/SPRINT_SCHEMA.md G5.3. Never deleted or reactivated: re-enrolling creates a new row (D-024).
 * Active on date d means enrolled_on <= d < withdrawn_on; withdrawn_on = enrolled_on is a
 * zero-length enrolment that was never active.
 */
#[Hidden(['id', 'class_id', 'student_id', 'institute_id', 'active_flag'])]
class ClassEnrollment extends Model
{
    use GuardsGeneratedColumns, HasPublicUuid, SerializesUtcDates;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'immutable_date:Y-m-d',
            'withdrawn_on' => 'immutable_date:Y-m-d',
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
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Active on calendar date $date (D-024 Q48): enrolled_on <= $date < withdrawn_on, whatever the
     * status. A zero-length enrolment (withdrawn_on = enrolled_on) is active on no date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOn(Builder $query, string $date): void
    {
        $query->where('enrolled_on', '<=', $date)
            ->where(fn (Builder $withdrawal) => $withdrawal->whereNull('withdrawn_on')->orWhere('withdrawn_on', '>', $date));
    }

    public function isActiveOn(string $date): bool
    {
        return $this->enrolled_on->format('Y-m-d') <= $date
            && ($this->withdrawn_on === null || $date < $this->withdrawn_on->format('Y-m-d'));
    }

    /**
     * @return BelongsTo<TuitionClass, $this>
     */
    public function tuitionClass(): BelongsTo
    {
        return $this->belongsTo(TuitionClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * @return BelongsTo<Institute, $this>
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'enrollment_id');
    }
}
