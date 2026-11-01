<?php

namespace Tests\Feature\Services;

use App\Exceptions\DomainRuleException;
use App\Models\TuitionClass;
use App\Services\ClassService;
use App\Services\EnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * E3a lock order (D-022 Pattern I, D-024): institutes → classes → class_fee_versions /
 * class_enrollments, each by primary key; no range locks or range writes; services authorize
 * again under the institute lock; unique-key backstops become 409s without the database error.
 */
class ClassLockingTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_class_create_locks_only_the_institute(): void
    {
        $institute = $this->makeInstitute();
        $admin = $this->adminOf($institute);
        $teacher = $this->teacherProfileOf($this->teacherAt($institute));

        $queries = $this->queriesDuring(fn () => $this->asActor($admin, fn () => app(ClassService::class)->create($admin, $institute, [
            'title' => 'Fictional Class', 'teacher_uuid' => $teacher->uuid, 'subject_code' => 'COMBINED_MATH', 'grade_code' => 'AL2',
            'exam_target_code' => 'AL_2027', 'medium_code' => 'sin', 'syllabus_code' => 'local', 'mode' => 'online',
            'max_students' => 10, 'starts_on' => '2026-11-01',
            'fee' => ['amount' => '1500.00', 'currency' => 'LKR', 'billing_cycle' => 'term', 'effective_from' => '2026-11-01'],
        ])));

        $this->assertSame([['institutes', 'for update']], $this->locks($queries));
    }

    public function test_class_update_with_a_fee_locks_institute_then_class_and_closes_the_fee_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $admin = $this->adminOf($institute);

        $queries = $this->queriesDuring(fn () => $this->asActor($admin, fn () => app(ClassService::class)->update($admin, $class, [
            'max_students' => 40,
            'fee' => ['amount' => '2750.00', 'currency' => 'LKR', 'billing_cycle' => 'monthly', 'effective_from' => '2026-12-01'],
        ])));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update']], $this->locks($queries));
        $this->assertWritesByPrimaryKey($queries, ['classes', 'class_fee_versions']);
    }

    public function test_enrolment_locks_institute_then_class(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);

        $queries = $this->queriesDuring(fn () => $this->asActor($staff, fn () => app(EnrollmentService::class)->enroll($staff, $class, $student->uuid)));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update']], $this->locks($queries));
    }

    public function test_withdrawal_locks_institute_then_class_then_the_enrolment_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $enrollment = $this->enrol($this->makeClass($institute));
        $staff = $this->staffOf($institute);

        $queries = $this->queriesDuring(fn () => $this->asActor($staff, fn () => app(EnrollmentService::class)->withdraw($staff, $enrollment, self::TODAY)));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update'], ['class_enrollments', 'for update']], $this->locks($queries));
        $enrollmentLock = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from `class_enrollments`') && str_ends_with($sql, 'for update')))[0];
        $this->assertStringContainsString('where `class_enrollments`.`id` = ?', $enrollmentLock);
        $this->assertWritesByPrimaryKey($queries, ['class_enrollments']);
    }

    public function test_services_authorize_again_under_the_institute_lock(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $staff = $this->staffOf($institute);
        $admin = $this->adminOf($institute);

        // Roles removed after the request's first check.
        DB::table('user_roles')->whereIn('user_id', [$staff->getKey(), $admin->getKey()])->delete();

        foreach ([
            fn () => app(EnrollmentService::class)->enroll($staff, $class, $this->makeStudent($institute)->uuid),
            fn () => app(ClassService::class)->update($admin, $class, ['title' => 'Changed']),
        ] as $call) {
            try {
                $this->asActor($staff, $call);
                $this->fail('Expected the former member to be refused.');
            } catch (AuthorizationException $exception) {
                $this->assertSame(404, $exception->status());
            }
        }

        // A suspension committed after the first check.
        $other = $this->staffOf($institute);
        $this->suspend($institute);
        $this->assertDomainRule(DomainRuleException::INSTITUTE_SUSPENDED, fn () => $this->asActor($other, fn () => app(EnrollmentService::class)->enroll($other, $class, $this->makeStudent($institute)->uuid)));

        $this->assertDatabaseCount('class_enrollments', 0);
        $this->assertSame($class->title, TuitionClass::query()->findOrFail($class->getKey())->title);
    }

    public function test_the_active_enrolment_unique_key_backstop_is_a_409_without_the_database_error(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $done = false;

        // Simulates a concurrent writer the locks would normally exclude.
        DB::connection()->beforeExecuting(function (string $sql) use (&$done, $class, $student, $institute) {
            if (! $done && str_starts_with($sql, 'insert into `class_enrollments`')) {
                $done = true;
                $this->insertEnrollment($class->getKey(), $student->getKey(), $institute->getKey());
            }
        });

        try {
            $this->asActor($staff, fn () => app(EnrollmentService::class)->enroll($staff, $class, $student->uuid));
            $this->fail('Expected already_active.');
        } catch (DomainRuleException $exception) {
            $this->assertSame(DomainRuleException::ALREADY_ACTIVE, $exception->reason);
            $this->assertNull($exception->getPrevious());
        }
    }

    /**
     * @return list<string>
     */
    private function queriesDuring(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    /**
     * @param  list<string>  $queries
     * @return list<array{0: string, 1: string}>
     */
    private function locks(array $queries): array
    {
        $locks = [];

        foreach ($queries as $sql) {
            foreach (['for update', 'lock in share mode'] as $mode) {
                if (str_ends_with($sql, $mode) && preg_match('/from `([a-z_]+)`/', $sql, $match) === 1) {
                    $locks[] = [$match[1], $mode];
                }
            }
        }

        return $locks;
    }

    /**
     * @param  list<string>  $queries
     * @param  list<string>  $tables
     */
    private function assertWritesByPrimaryKey(array $queries, array $tables): void
    {
        foreach ($queries as $sql) {
            foreach ($tables as $table) {
                if (str_starts_with($sql, "update `{$table}`") || str_starts_with($sql, "delete from `{$table}`")) {
                    $this->assertMatchesRegularExpression("/where (`{$table}`\\.)?`id` = \\?$/", $sql);
                }
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function asActor($actor, callable $callback): mixed
    {
        $this->useAuditContext($actor->getKey());

        return $callback();
    }
}
