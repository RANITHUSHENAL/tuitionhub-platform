<?php

namespace Tests\Feature\Http\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Proves a read endpoint writes nothing (E2b; D-028): every row of the tables it could touch, and
 * the audit log, are identical before and after the request.
 */
trait AssertsReadOnly
{
    /** Tables a guardian, student or teacher read could plausibly write to. */
    private const READ_ONLY_TABLES = [
        'users',
        'user_roles',
        'teacher_profiles',
        'guardian_profiles',
        'student_profiles',
        'student_guardians',
        'guardian_invitations',
        'audit_logs',
    ];

    /**
     * @param  callable(): TestResponse  $request
     */
    protected function assertReadOnly(callable $request): TestResponse
    {
        $before = $this->snapshotTables();
        $response = $request();
        $this->assertSame($before, $this->snapshotTables(), 'A read-only endpoint changed stored data.');

        return $response;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshotTables(): array
    {
        $snapshot = [];

        foreach (self::READ_ONLY_TABLES as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
        }

        return $snapshot;
    }
}
