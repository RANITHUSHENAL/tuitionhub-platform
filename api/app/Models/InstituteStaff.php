<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/SPRINT_SCHEMA.md G3.2. Written only by App\Services\StaffService; history is kept as former rows.
 */
#[Table('institute_staff')]
#[Hidden(['id', 'institute_id', 'user_id', 'active_flag'])]
class InstituteStaff extends Model
{
    use GuardsGeneratedColumns, SerializesUtcDates;

    public const POSITIONS = ['manager', 'clerk', 'receptionist'];

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
     * @return BelongsTo<Institute, $this>
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
