<?php

namespace App\Models;

use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * docs/SPRINT_SCHEMA.md G1.7. The three stored roles are seeded by their migration and are read-only.
 */
class Role extends Model
{
    use SerializesUtcDates;

    public const SUPER_ADMIN = 'super_admin';

    public const INSTITUTE_ADMIN = 'institute_admin';

    public const INSTITUTE_STAFF = 'institute_staff';

    public const NAMES = [self::SUPER_ADMIN, self::INSTITUTE_ADMIN, self::INSTITUTE_STAFF];

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_INSTITUTE = 'institute';

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Roles are seeded and read-only.'));
        static::deleting(fn () => throw new LogicException('Roles are seeded and read-only.'));
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }
}
