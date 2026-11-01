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

    protected const MISSING_DEFAULT = 1364;

    protected const INVALID_JSON = 3140;

    protected const GENERATED_COLUMN_WRITE = 3105;

    private int $fixturePhoneSequence = 0;

    private int $fixtureSequence = 0;

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

    /**
     * Lookup tables start empty in tests; values follow the contract examples.
     */
    protected function gradeId(string $code = 'G11'): int
    {
        return $this->lookupId('grades', $code, ['name' => 'Grade 11', 'level' => 'ol', 'sort_order' => 11]);
    }

    protected function mediumId(string $code = 'sin'): int
    {
        return $this->lookupId('mediums', $code, ['name' => 'Sinhala']);
    }

    protected function insertGuardianProfile(array $overrides = []): int
    {
        return DB::table('guardian_profiles')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $overrides['user_id'] ?? $this->insertUser(),
        ], $this->timestamps(), $overrides));
    }

    protected function insertStudentProfile(int $instituteId, array $overrides = []): int
    {
        $this->fixtureSequence++;

        return DB::table('student_profiles')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'managing_institute_id' => $instituteId,
            'created_by_user_id' => $overrides['created_by_user_id'] ?? $this->insertUser(),
            'full_name' => "Fictional Student {$this->fixtureSequence}",
            'grade_id' => $overrides['grade_id'] ?? $this->gradeId(),
            'medium_id' => $overrides['medium_id'] ?? $this->mediumId(),
        ], $this->timestamps(), $overrides));
    }

    /**
     * code_hash is a placeholder digest of a fixture label, not an HMAC of a real code.
     */
    protected function insertInvitation(int $studentId, int $instituteId, array $overrides = []): int
    {
        $this->fixtureSequence++;

        return DB::table('guardian_invitations')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'student_id' => $studentId,
            'institute_id' => $instituteId,
            'invited_by_user_id' => $overrides['invited_by_user_id'] ?? $this->insertUser(),
            'guardian_phone' => sprintf('+9471%07d', $this->fixtureSequence),
            'relationship' => 'mother',
            'code_hash' => hash('sha256', "fixture-{$this->fixtureSequence}"),
            'status' => 'pending',
            'expires_at' => now('UTC')->addHours(48),
        ], $this->timestamps(), $overrides));
    }

    protected function insertGuardianLink(int $studentId, int $guardianId, array $overrides = []): int
    {
        return DB::table('student_guardians')->insertGetId(array_merge([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship' => 'mother',
            'is_primary' => 0,
            'linked_by_user_id' => $overrides['linked_by_user_id'] ?? $this->insertUser(),
            'linked_at' => now('UTC'),
            'invitation_id' => null,
        ], $this->timestamps(), $overrides));
    }

    protected function revokeGuardianLink(int $linkId): void
    {
        DB::table('student_guardians')->where('id', $linkId)->update([
            'revoked_at' => now('UTC'),
            'revoked_by_user_id' => $this->insertUser(),
        ]);
    }

    /**
     * A fictional class at the institute, with its own teacher and the default lookups (G5.1).
     */
    protected function insertClass(int $instituteId, array $overrides = []): int
    {
        $this->fixtureSequence++;

        return DB::table('classes')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'institute_id' => $instituteId,
            'teacher_id' => $overrides['teacher_id'] ?? $this->insertTeacherProfile(),
            'subject_id' => $overrides['subject_id'] ?? $this->lookupId('subjects', 'COMBINED_MATH', ['name' => 'Combined Mathematics', 'category' => 'core']),
            'grade_id' => $overrides['grade_id'] ?? $this->gradeId(),
            'stream_id' => null,
            'exam_target_id' => $overrides['exam_target_id'] ?? $this->lookupId('exam_targets', 'AL_2027', ['name' => 'A/L 2027', 'exam' => 'al', 'year' => 2027]),
            'medium_id' => $overrides['medium_id'] ?? $this->mediumId(),
            'syllabus_id' => $overrides['syllabus_id'] ?? $this->lookupId('syllabi', 'local', ['name' => 'Local (National)']),
            'title' => "Fictional Class {$this->fixtureSequence}",
            'mode' => 'in_person',
            'max_students' => 30,
            'starts_on' => '2026-11-01',
            'ends_on' => null,
            'status' => 'active',
            'public_listing' => 0,
        ], $this->timestamps(), $overrides));
    }

    protected function insertFeeVersion(int $classId, array $overrides = []): int
    {
        return DB::table('class_fee_versions')->insertGetId(array_merge([
            'class_id' => $classId,
            'amount' => '2500.00',
            'currency' => 'LKR',
            'billing_cycle' => 'monthly',
            'effective_from' => '2026-11-01',
            'effective_to' => null,
            'created_by_user_id' => $overrides['created_by_user_id'] ?? $this->insertUser(),
        ], $this->timestamps(), $overrides));
    }

    protected function insertSession(int $classId, array $overrides = []): int
    {
        return DB::table('class_sessions')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'class_id' => $classId,
            'session_date' => '2026-11-03',
            'start_time' => '15:30:00',
            'end_time' => '17:30:00',
            'status' => 'scheduled',
            'created_by_user_id' => $overrides['created_by_user_id'] ?? $this->insertUser(),
        ], $this->timestamps(), $overrides));
    }

    /**
     * $instituteId must be both the class's and the student's institute, or the composite FKs refuse it.
     */
    protected function insertEnrollment(int $classId, int $studentId, int $instituteId, array $overrides = []): int
    {
        return DB::table('class_enrollments')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'class_id' => $classId,
            'student_id' => $studentId,
            'institute_id' => $instituteId,
            'status' => 'active',
            'enrolled_on' => '2026-11-01',
            'withdrawn_on' => null,
        ], $this->timestamps(), $overrides));
    }

    protected function withdrawEnrollment(int $enrollmentId, string $withdrawnOn = '2026-12-01'): void
    {
        DB::table('class_enrollments')->where('id', $enrollmentId)->update([
            'status' => 'withdrawn',
            'withdrawn_on' => $withdrawnOn,
            'updated_at' => now('UTC'),
        ]);
    }

    protected function insertAttendance(int $sessionId, int $enrollmentId, int $classId, array $overrides = []): int
    {
        return DB::table('attendances')->insertGetId(array_merge([
            'session_id' => $sessionId,
            'enrollment_id' => $enrollmentId,
            'class_id' => $classId,
            'status' => 'present',
            'method' => 'manual',
            'notes' => null,
            'marked_by_user_id' => $overrides['marked_by_user_id'] ?? $this->insertUser(),
            'marked_at' => now('UTC'),
        ], $this->timestamps(), $overrides));
    }

    protected function insertAuditLog(array $overrides = []): int
    {
        return DB::table('audit_logs')->insertGetId(array_merge([
            'user_id' => null,
            'institute_id' => null,
            'action' => 'student.created',
            'auditable_type' => 'student_profile',
            'auditable_id' => 1,
            'created_at' => now('UTC'),
        ], $overrides));
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

    private function lookupId(string $table, string $code, array $attributes): int
    {
        $id = DB::table($table)->where('code', $code)->value('id');

        return (int) ($id ?? DB::table($table)->insertGetId(array_merge(['code' => $code], $attributes, $this->timestamps())));
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
