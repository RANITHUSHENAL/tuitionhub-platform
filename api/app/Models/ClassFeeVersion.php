<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * docs/SPRINT_SCHEMA.md G5.2. Append-only fee history (contract §7.4; D-024 Q43): a stored version
 * never changes, except that effective_to is set once, from NULL, when the next version starts.
 * Versions are never deleted. Invoices snapshot the amount. No public UUID: no route addresses a version.
 */
#[Hidden(['id', 'class_id', 'created_by_user_id', 'current_flag'])]
class ClassFeeVersion extends Model
{
    use GuardsGeneratedColumns, SerializesUtcDates;

    public const BILLING_CYCLES = ['monthly', 'term', 'one_time'];

    /** Fixed once stored. */
    private const IMMUTABLE = ['class_id', 'amount', 'currency', 'billing_cycle', 'effective_from', 'created_by_user_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'immutable_date:Y-m-d',
            'effective_to' => 'immutable_date:Y-m-d',
        ];
    }

    protected function generatedColumns(): array
    {
        return ['current_flag'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            foreach (self::IMMUTABLE as $column) {
                if ($version->isDirty($column)) {
                    throw new LogicException("Fee version [{$column}] cannot change; create a new version instead.");
                }
            }

            if ($version->isDirty('effective_to') && $version->getRawOriginal('effective_to') !== null) {
                throw new LogicException('A closed fee version cannot be changed.');
            }
        });

        static::deleting(fn () => throw new LogicException('Fee versions are append-only.'));
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
}
