<?php

namespace Tests\Feature\Services;

use App\Models\TeacherProfile;
use App\Services\TeacherProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\AppFixtures;
use Tests\TestCase;

/**
 * C-1 (D-022): TeacherProfileService locks the teacher_profiles row (by primary key) and reads the
 * account status with a shared lock; it never takes an exclusive lock on users.
 */
class TeacherProfileLockingTest extends TestCase
{
    use AppFixtures;
    use RefreshDatabase;

    public function test_creating_and_updating_never_lock_the_users_row_exclusively(): void
    {
        $user = $this->makeUser();

        $create = $this->queriesDuring(fn () => app(TeacherProfileService::class)
            ->upsert($user, ['display_name' => 'Mr. Fictional Teacher']));
        $update = $this->queriesDuring(fn () => app(TeacherProfileService::class)
            ->upsert($user, ['years_experience' => 3]));

        foreach ([$create, $update] as $queries) {
            $usersQueries = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from `users`')));

            $this->assertNotEmpty($usersQueries);
            foreach ($usersQueries as $sql) {
                $this->assertStringNotContainsString('for update', $sql);
                $this->assertMatchesRegularExpression('/lock in share mode|for share/', $sql, 'Status must be a current (locking) read.');
            }
        }

        // An existing profile is locked by primary key, never by a range or gap on user_id.
        $profileLocks = array_values(array_filter($update, fn (string $sql) => str_contains($sql, '`teacher_profiles`') && str_contains($sql, 'for update')));
        $this->assertCount(1, $profileLocks);
        $this->assertStringContainsString('where `teacher_profiles`.`id` = ?', $profileLocks[0]);

        $this->assertSame(3, TeacherProfile::query()->where('user_id', $user->getKey())->sole()->years_experience);
    }

    public function test_an_account_suspended_after_the_request_was_authorized_is_still_refused(): void
    {
        $user = $this->makeUser();
        // The model the request holds still says active; the database says suspended.
        DB::table('users')->where('id', $user->getKey())->update(['status' => 'suspended']);

        try {
            app(TeacherProfileService::class)->upsert($user, ['display_name' => 'Mr. Fictional Teacher']);
            $this->fail('Expected the suspended account to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This account is suspended.', $exception->getMessage());
        }

        $this->assertDatabaseCount('teacher_profiles', 0);
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
}
