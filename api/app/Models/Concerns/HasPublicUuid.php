<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use LogicException;

/**
 * Server-owned public UUID in the separate `uuid` column (docs/SPRINT_SCHEMA.md §0.3).
 *
 * The integer `id` stays the auto-incrementing primary key. Laravel's HasUuids is not used:
 * it targets the primary key and generates UUIDv7 (HasVersion4Uuids is timestamp-first COMB).
 * This trait generates a random RFC 4122 version-4 UUID through Laravel's unique-ID hook,
 * which runs on every insert, including saveQuietly().
 */
trait HasPublicUuid
{
    public function initializeHasPublicUuid(): void
    {
        $this->usesUniqueIds = true;
    }

    public static function bootHasPublicUuid(): void
    {
        static::updating(function (self $model) {
            if ($model->isDirty('uuid')) {
                throw new LogicException('Public UUIDs are server-owned and cannot change.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Always overwrite: a client- or caller-supplied UUID is never kept.
     */
    public function setUniqueIds(): void
    {
        $this->setAttribute('uuid', $this->newUniqueId());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === 'uuid' && ! Str::isUuid($value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
