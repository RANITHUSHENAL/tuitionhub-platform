<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/SPRINT_SCHEMA.md G5.4. session_date, start_time and end_time are Asia/Colombo values and
 * never time-zone converted (start_time/end_time stay "HH:MM:SS" strings). A cancelled session is
 * kept and frees its slot (D-024 Q39).
 */
#[Hidden(['id', 'class_id', 'created_by_user_id', 'slot_flag'])]
class ClassSession extends Model
{
    use GuardsGeneratedColumns, HasPublicUuid, SerializesUtcDates;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'session_date' => 'immutable_date:Y-m-d',
        ];
    }

    protected function generatedColumns(): array
    {
        return ['slot_flag'];
    }

    /**
     * @return BelongsTo<TuitionClass, $this>
     */
    public function tuitionClass(): BelongsTo
    {
        return $this->belongsTo(TuitionClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }
}
