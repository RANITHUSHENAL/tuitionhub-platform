<?php

namespace Tests\Feature\Schema\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Direct query-builder fixtures for database constraint tests (docs/SPRINT_SCHEMA.md validation plan).
 *
 * All values are fictional. Timestamps are set explicitly in UTC because query-builder inserts bypass Eloquent.
 */
trait SchemaFixtures
{
    // MySQL error codes asserted by the constraint tests.
    protected const DUPLICATE_KEY = 1062;

    protected const CHECK_VIOLATED = 3819;

    protected const NO_REFERENCED_ROW = 1452;

    protected const ROW_IS_REFERENCED = 1451;

    private int $fixturePhoneSequence = 0;

    protected function insertUser(array $overrides = []): int
    {
        $this->fixturePhoneSequence++;

        return DB::table('users')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'phone' => sprintf('+9470%07d', $this->fixturePhoneSequence),
            'status' => 'active',
        ], $this->timestamps(), $overrides));
    }

    protected function insertInstitute(array $overrides = []): int
    {
        return DB::table('institutes')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $overrides['owner_user_id'] ?? $this->insertUser(),
            'name' => 'Fictional Test Academy',
            'status' => 'active',
            'district' => 'Colombo',
        ], $this->timestamps(), $overrides));
    }

    protected function insertTeacherProfile(array $overrides = []): int
    {
        return DB::table('teacher_profiles')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $overrides['user_id'] ?? $this->insertUser(),
            'display_name' => 'Fictional Teacher',
        ], $this->timestamps(), $overrides));
    }

    protected function insertUserRole(int $userId, string $roleName, ?int $instituteId, array $overrides = []): int
    {
        return DB::table('user_roles')->insertGetId(array_merge([
            'user_id' => $userId,
            'role_id' => $this->roleId($roleName),
            'role_scope' => $roleName === 'super_admin' ? 'global' : 'institute',
            'institute_id' => $instituteId,
        ], $this->timestamps(), $overrides));
    }

    protected function insertStaff(int $instituteId, int $userId, array $overrides = []): int
    {
        return DB::table('institute_staff')->insertGetId(array_merge([
            'institute_id' => $instituteId,
            'user_id' => $userId,
            'position' => 'manager',
            'status' => 'active',
            'joined_on' => '2026-10-01',
            'left_on' => null,
        ], $this->timestamps(), $overrides));
    }

    protected function insertAssignment(int $teacherId, int $instituteId, array $overrides = []): int
    {
        return DB::table('teacher_institute')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'teacher_id' => $teacherId,
            'institute_id' => $instituteId,
            'joined_on' => '2026-10-01',
            'left_on' => null,
            'status' => 'active',
        ], $this->timestamps(), $overrides));
    }

    protected function roleId(string $name): int
    {
        return (int) DB::table('roles')->where('name', $name)->value('id');
    }

    /**
     * Assert that the statement fails with the given MySQL error code and names the given constraint.
     */
    protected function assertConstraintViolation(callable $statement, int $mysqlCode, string $constraint): void
    {
        try {
            $statement();
        } catch (QueryException $exception) {
            $this->assertSame($mysqlCode, $exception->errorInfo[1] ?? null, $exception->getMessage());
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected MySQL error {$mysqlCode} on {$constraint}, but the statement succeeded.");
    }

    /**
     * @return array{created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon}
     */
    private function timestamps(): array
    {
        $now = now('UTC');

        return ['created_at' => $now, 'updated_at' => $now];
    }
}
