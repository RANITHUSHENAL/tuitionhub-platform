<?php

namespace App\Models;

use App\Models\Concerns\GuardsGeneratedColumns;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/SPRINT_SCHEMA.md G2.2. Written only by App\Services\RoleAssignmentService (D-011, D-015).
 */
#[Hidden(['id', 'user_id', 'role_id', 'institute_id', 'scope_key'])]
class UserRole extends Model
{
    use GuardsGeneratedColumns, SerializesUtcDates;

    protected function generatedColumns(): array
    {
        return ['scope_key'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Institute, $this>
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
