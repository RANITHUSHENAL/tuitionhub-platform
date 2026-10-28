<?php

namespace App\Audit;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Query builder for App\Models\AuditLog. Every Eloquent update or delete path throws, so audit
 * rows stay append-only even for quiet saves and bulk queries. Raw DB::table('audit_logs') writes
 * are outside Eloquent and remain a code-review rule (D-015 Q33: no triggers).
 *
 * @extends Builder<\App\Models\AuditLog>
 */
class AuditLogBuilder extends Builder
{
    public function update(array $values)
    {
        $this->refuse();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->refuse();
    }

    public function touch($column = null)
    {
        $this->refuse();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->refuse();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->refuse();
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->refuse();
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->refuse();
    }

    public function delete()
    {
        $this->refuse();
    }

    public function forceDelete()
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new LogicException('Audit logs are append-only.');
    }
}
