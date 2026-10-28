<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/SPRINT_SCHEMA.md G2.5. Guardian access comes only from active links, never from this row.
 */
#[Hidden(['id', 'user_id'])]
class GuardianProfile extends Model
{
    use HasPublicUuid, SerializesUtcDates;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'guardian_id');
    }
}
