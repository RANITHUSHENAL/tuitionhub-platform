<?php

namespace Tests\Feature\Services;

use App\Exceptions\DomainRuleException;
use App\Services\AttendanceService;
use App\Services\SessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * E3b lock order (D-022 Pattern I, D-024): institutes → classes → class_sessions, each by primary
 * key; enrolments and attendance are plain reads after the class lock and change by primary key;
 * no range locks. Services authorize again under the institute lock; the slot unique key is a
 * 409 without the database error.
 */
class SessionLockingTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_session_create_locks_institute_then_class(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $admin = $this->adminOf($institute);

        $queries = $this->queriesDuring($admin, fn () => app(SessionService::class)->create($admin, $class, [
            'session_date' => '2026-11-12', 'start_time' => '15:30', 'end_time' => '17:30',
        ]));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update']], $this->locks($queries));
    }

    public function test_session_cancel_locks_institute_class_then_session_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $session = $this->makeSession($this->makeClass($institute));
        $admin = $this->adminOf($institute);

        $queries = $this->queriesDuring($admin, fn () => app(SessionService::class)->cancel($admin, $session));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update'], ['class_sessions', 'for update']], $this->locks($queries));
        $this->assertWritesByPrimaryKey($queries, 'class_sessions');
    }

    public function test_marking_locks_institute_class_then_session_and_corrects_rows_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);
        $enrollment = $this->enrol($class);
        $this->mark($session, $enrollment, ['status' => 'absent']);
        $admin = $this->adminOf($institute);

        $queries = $this->queriesDuring($admin, fn () => app(AttendanceService::class)->mark($admin, $session, [
            ['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual'],
        ]));

        $this->assertSame([['institutes', 'for update'], ['classes', 'for update'], ['class_sessions', 'for update']], $this->locks($queries));
        $this->assertWritesByPrimaryKey($queries, 'attendances');
        $this->assertSame('present', DB::table('attendances')->value('status'));
    }

    public function test_services_authorize_again_under_the_institute_lock(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $session = $this->makeSession($class);
        $enrollment = $this->enrol($class);
        $teacher = $this->teacherUserOf($class);

        // The teacher's assignment ended after the request's first check.
        DB::table('teacher_institute')->where('teacher_id', $class->teacher_id)->update(['status' => 'former', 'left_on' => '2026-11-09']);

        foreach ([
            fn () => app(SessionService::class)->create($teacher, $class, ['session_date' => '2026-11-12', 'start_time' => '15:30', 'end_time' => '17:30']),
            fn () => app(SessionService::class)->cancel($teacher, $session),
            fn () => app(AttendanceService::class)->mark($teacher, $session, [['enrollment_uuid' => $enrollment->uuid, 'status' => 'present', 'method' => 'manual']]),
        ] as $call) {
            try {
                $this->queriesDuring($teacher, $call);
                $this->fail('Expected the former teacher to be refused.');
            } catch (AuthorizationException $exception) {
                $this->assertSame(404, $exception->status());
            }
        }

        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame(1, DB::table('class_sessions')->where('status', 'scheduled')->count());
    }

    public function test_the_slot_unique_key_backstop_is_a_409_without_the_database_error(): void
    {
        $institute = $this->makeInstitute();
        $class = $this->makeClass($institute);
        $admin = $this->adminOf($institute);
        $done = false;

        // Simulates a concurrent writer the locks would normally exclude.
        DB::connection()->beforeExecuting(function (string $sql) use (&$done, $class) {
            if (! $done && str_starts_with($sql, 'insert into `class_sessions`')) {
                $done = true;
                $this->insertSession($class->getKey(), ['session_date' => '2026-11-12']);
            }
        });

        try {
            $this->queriesDuring($admin, fn () => app(SessionService::class)->create($admin, $class, [
                'session_date' => '2026-11-12', 'start_time' => '15:30', 'end_time' => '17:30',
            ]));
            $this->fail('Expected session_slot_taken.');
        } catch (DomainRuleException $exception) {
            $this->assertSame(DomainRuleException::SESSION_SLOT_TAKEN, $exception->reason);
            $this->assertNull($exception->getPrevious());
        }
    }

    /**
     * @return list<string>
     */
    private function queriesDuring($actor, callable $callback): array
    {
        $this->useAuditContext($actor->getKey());
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
        }

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
     */
    private function assertWritesByPrimaryKey(array $queries, string $table): void
    {
        $writes = array_filter($queries, fn (string $sql) => str_starts_with($sql, "update `{$table}`"));
        $this->assertNotEmpty($writes);

        foreach ($writes as $sql) {
            $this->assertMatchesRegularExpression("/where (`{$table}`\\.)?`id` = \\?$/", $sql);
        }
    }
}
