<?php

namespace Tests\Feature\Services;

use App\Exceptions\DomainRuleException;
use App\Models\GuardianInvitation;
use App\Models\GuardianProfile;
use App\Services\GuardianInvitationService;
use App\Services\GuardianLinkService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Http\Concerns\GuardianFixtures;
use Tests\TestCase;

/**
 * E2 lock order (D-022 Pattern I, D-023): institutes → student_profiles → guardian_invitations →
 * guardian_profiles → student_guardians, each by primary key; users only shared. No range locks and
 * no range updates. Services authorize again under the institute lock, and unique-key backstops
 * become 409s without chaining the database error. Checked through the executed SQL; real
 * two-connection concurrency is the open E2c item.
 */
class GuardianLockingTest extends TestCase
{
    use GuardianFixtures;
    use RefreshDatabase;

    public function test_create_locks_the_institute_then_the_student_and_never_a_range(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);

        $queries = $this->queriesDuring(fn () => $this->issueInvitation($staff, $student, '+94771234567'));

        $this->assertSame([
            ['institutes', 'for update'],
            ['student_profiles', 'for update'],
        ], $this->locks($queries));
        $this->assertNoRangeWrites($queries, 'guardian_invitations');
    }

    public function test_an_expired_pending_invitation_is_retired_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $this->issueInvitation($staff, $student, '+94771234567');
        $this->travel(49)->hours();

        $queries = $this->queriesDuring(fn () => $this->issueInvitation($staff, $student, '+94771234567'));

        $updates = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'update `guardian_invitations`')));
        $this->assertCount(1, $updates);
        $this->assertMatchesRegularExpression('/where `guardian_invitations`\.`id` = \?$/', $updates[0]);
        $this->assertSame(['expired' => 1, 'pending' => 1], GuardianInvitation::query()->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all());
    }

    public function test_accept_locks_institute_student_invitation_then_both_users_shared_in_key_order(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [, $code] = $this->issueInvitation($staff, $student, $parent->phone);

        $queries = $this->queriesDuring(fn () => $this->asActor($parent, fn () => app(GuardianInvitationService::class)->accept($parent, $code)));

        $this->assertSame([
            ['institutes', 'for update'],
            ['student_profiles', 'for update'],
            ['guardian_invitations', 'for update'],
            ['users', 'lock in share mode'],
        ], $this->locks($queries));

        $usersLock = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from `users`') && str_contains($sql, 'lock in share mode')))[0];
        // Integer keys are inlined by whereKey(); the rows are locked in primary-key order.
        $ids = [$staff->getKey(), $parent->getKey()];
        sort($ids);
        $this->assertSame(
            "where `users`.`id` in ({$ids[0]}, {$ids[1]}) order by `id` asc lock in share mode",
            substr($usersLock, strpos($usersLock, 'where ')),
        );
        $this->assertNoRangeWrites($queries, 'guardian_invitations');
        $this->assertNoRangeWrites($queries, 'student_guardians');
    }

    public function test_revoking_an_invitation_locks_institute_student_then_invitation(): void
    {
        $institute = $this->makeInstitute();
        $staff = $this->staffOf($institute);
        [$invitation] = $this->issueInvitation($staff, $this->makeStudent($institute), '+94771234567');

        $queries = $this->queriesDuring(fn () => $this->asActor($staff, fn () => app(GuardianInvitationService::class)->revoke($staff, $invitation)));

        $this->assertSame([
            ['institutes', 'for update'],
            ['student_profiles', 'for update'],
            ['guardian_invitations', 'for update'],
        ], $this->locks($queries));
        $this->assertNoRangeWrites($queries, 'guardian_invitations');
    }

    public function test_revoking_a_link_locks_institute_student_then_links_by_primary_key(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $admin = $this->adminOf($institute);
        [$a, $b] = [$this->makeParent(), $this->makeParent()];
        $this->linkParent($staff, $student, $a);
        $this->linkParent($staff, $student, $b, 'father');

        $queries = $this->queriesDuring(fn () => $this->asActor($admin, fn () => app(GuardianLinkService::class)->revoke($admin, $student, $a)));

        // The revoked link, then the promoted one: both by primary key, after institute and student.
        $this->assertSame([
            ['institutes', 'for update'],
            ['student_profiles', 'for update'],
            ['student_guardians', 'for update'],
            ['student_guardians', 'for update'],
        ], $this->locks($queries));
        foreach (array_filter($queries, fn (string $sql) => str_contains($sql, 'from `student_guardians`') && str_contains($sql, 'for update')) as $sql) {
            $this->assertStringContainsString('where `student_guardians`.`id` = ?', $sql);
        }
        $this->assertNoRangeWrites($queries, 'student_guardians');
    }

    public function test_create_authorizes_again_under_the_institute_lock(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);

        // A suspension committed after the request's first check.
        $this->suspend($institute);
        $this->assertDomainRule(DomainRuleException::INSTITUTE_SUSPENDED, fn () => $this->issueInvitation($staff, $student, '+94771234567'));

        // A role removed after the request's first check.
        DB::table('institutes')->where('id', $institute->getKey())->update(['status' => 'active']);
        DB::table('user_roles')->where('user_id', $staff->getKey())->delete();
        try {
            $this->issueInvitation($staff, $student, '+94771234567');
            $this->fail('Expected the former staff member to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertDatabaseCount('guardian_invitations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_link_revocation_authorizes_again_under_the_institute_lock(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $parent = $this->makeParent();
        $this->linkParent($this->staffOf($institute), $student, $parent);
        $admin = $this->adminOf($institute);
        DB::table('user_roles')->where('user_id', $admin->getKey())->delete();

        try {
            $this->asActor($admin, fn () => app(GuardianLinkService::class)->revoke($admin, $student, $parent));
            $this->fail('Expected the former admin to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertSame(1, DB::table('student_guardians')->whereNull('revoked_at')->count());
    }

    public function test_the_pending_unique_key_backstop_is_a_409_without_the_database_error(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $phone = '+94771234567';

        // Simulates a concurrent writer the locks would normally exclude: a pending row for the same
        // student and phone appears just before the insert.
        $this->beforeFirstInsertInto('guardian_invitations', fn () => $this->insertInvitation($student->getKey(), $institute->getKey(), ['guardian_phone' => $phone]));

        try {
            $this->issueInvitation($staff, $student, $phone);
            $this->fail('Expected invitation_pending.');
        } catch (DomainRuleException $exception) {
            $this->assertSame(DomainRuleException::INVITATION_PENDING, $exception->reason);
            // Never chained: MySQL's duplicate-entry text contains the guardian's phone (D-022).
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString($phone, $exception->getMessage());
        }
    }

    public function test_the_active_link_unique_key_backstop_is_a_409_without_the_database_error(): void
    {
        $institute = $this->makeInstitute();
        $student = $this->makeStudent($institute);
        $staff = $this->staffOf($institute);
        $parent = $this->makeParent();
        [, $code] = $this->issueInvitation($staff, $student, $parent->phone);
        $profileId = $this->insertGuardianProfile(['user_id' => $parent->getKey()]);

        $this->beforeFirstInsertInto('student_guardians', fn () => $this->insertGuardianLink($student->getKey(), $profileId));

        try {
            $this->asActor($parent, fn () => app(GuardianInvitationService::class)->accept($parent, $code));
            $this->fail('Expected link_already_active.');
        } catch (DomainRuleException $exception) {
            $this->assertSame(DomainRuleException::LINK_ALREADY_ACTIVE, $exception->reason);
            $this->assertNull($exception->getPrevious());
        }

        $this->assertSame(1, GuardianProfile::query()->count());
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
     * Every explicit locking read, in execution order: [table, mode].
     *
     * @param  list<string>  $queries
     * @return list<array{0: string, 1: string}>
     */
    private function locks(array $queries): array
    {
        $locks = [];

        foreach ($queries as $sql) {
            foreach (['for update', 'lock in share mode', 'for share'] as $mode) {
                if (str_ends_with($sql, $mode) && preg_match('/from `([a-z_]+)`/', $sql, $match) === 1) {
                    $locks[] = [$match[1], $mode];
                }
            }
        }

        return $locks;
    }

    /**
     * No UPDATE or DELETE on the table may select rows by anything other than the primary key.
     *
     * @param  list<string>  $queries
     */
    private function assertNoRangeWrites(array $queries, string $table): void
    {
        foreach ($queries as $sql) {
            if (str_starts_with($sql, "update `{$table}`") || str_starts_with($sql, "delete from `{$table}`")) {
                $this->assertMatchesRegularExpression("/where (`{$table}`\\.)?`id` (= \\?|in \\([0-9?, ]+\\))$/", $sql);
            }
        }
    }

    /**
     * Runs $callback once, right before the first INSERT into $table.
     */
    private function beforeFirstInsertInto(string $table, callable $callback): void
    {
        $done = false;

        DB::connection()->beforeExecuting(function (string $sql) use ($table, $callback, &$done) {
            if (! $done && str_starts_with($sql, "insert into `{$table}`")) {
                $done = true;
                $callback();
            }
        });
    }
}
