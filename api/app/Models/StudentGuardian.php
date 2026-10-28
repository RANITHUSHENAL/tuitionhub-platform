<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/SPRINT_SCHEMA.md G4.3. Never deleted or reactivated; is_primary is set only by the S2 primary rules.
 */
#[Hidden(['id', 'student_id', 'guardian_id', 'linked_by_user_id', 'revoked_by_user_id', 'invitation_id', 'active_flag', 'primary_flag'])]
class StudentGuardian extends Model
{
    use GuardsGeneratedColumns, SerializesUtcDates;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'linked_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected function generatedColumns(): array
    {
        return ['active_flag', 'primary_flag'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * @return BelongsTo<GuardianProfile, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return BelongsTo<GuardianInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(GuardianInvitation::class, 'invitation_id');
    }
}
