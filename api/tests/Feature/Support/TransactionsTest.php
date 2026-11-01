<?php

namespace Tests\Feature\Support;

use App\Exceptions\DomainRuleException;
use App\Exceptions\ServiceBusyException;
use App\Support\Transactions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\FakeDatabaseErrors;
use Tests\TestCase;
use Throwable;

/**
 * App\Support\Transactions::retrying (D-022). These tests run at the outermost transaction level
 * (no RefreshDatabase), because only an outermost transaction can be retried. They write only to
 * a session-scoped temporary table on the test database.
 */
class TransactionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('CREATE TEMPORARY TABLE h1_probe (id INT AUTO_INCREMENT PRIMARY KEY, attempt INT NOT NULL)');
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS h1_probe');

        parent::tearDown();
    }

    public function test_a_deadlock_is_retried_and_the_failed_attempt_is_rolled_back(): void
    {
        $attempts = 0;

        $result = Transactions::retrying(function () use (&$attempts) {
            $attempts++;
            DB::table('h1_probe')->insert(['attempt' => $attempts]);

            if ($attempts === 1) {
                throw FakeDatabaseErrors::deadlock();
            }

            return 'committed';
        });

        $this->assertSame('committed', $result);
        $this->assertSame(2, $attempts);
        // Only the committed attempt's write exists.
        $this->assertSame([2], DB::table('h1_probe')->pluck('attempt')->all());
    }

    public function test_it_gives_up_after_three_deadlocks_with_service_busy(): void
    {
        Log::shouldReceive('warning')->once()->with('db.service_busy', ['reason' => 'deadlock', 'attempts' => 3]);
        $attempts = 0;

        try {
            Transactions::retrying(function () use (&$attempts) {
                $attempts++;
                DB::table('h1_probe')->insert(['attempt' => $attempts]);

                throw FakeDatabaseErrors::deadlock();
            }, 10);
            $this->fail('Expected ServiceBusyException.');
        } catch (ServiceBusyException $exception) {
            $this->assertSame('The service is busy. Please try again.', $exception->getMessage());
        }

        // Capped at three attempts, and nothing from any attempt was committed.
        $this->assertSame(3, $attempts);
        $this->assertSame(0, DB::table('h1_probe')->count());
    }

    public function test_a_lock_wait_timeout_is_not_retried(): void
    {
        $attempts = 0;

        $this->expectException(ServiceBusyException::class);

        try {
            Transactions::retrying(function () use (&$attempts) {
                $attempts++;

                throw FakeDatabaseErrors::lockWaitTimeout();
            });
        } finally {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_validation_authorization_business_and_unique_key_errors_are_never_retried(): void
    {
        $errors = [
            new RuntimeException('Fictional failure.'),
            ValidationException::withMessages(['name' => 'Invalid.']),
            new AuthorizationException('Denied.'),
            new DomainRuleException(DomainRuleException::ALREADY_ACTIVE),
            FakeDatabaseErrors::uniqueViolation(),
        ];

        foreach ($errors as $error) {
            $attempts = 0;

            try {
                Transactions::retrying(function () use (&$attempts, $error) {
                    $attempts++;

                    throw $error;
                });
                $this->fail('Expected the original exception.');
            } catch (Throwable $thrown) {
                $this->assertSame($error, $thrown, $error::class);
                $this->assertSame(1, $attempts, $error::class);
            }
        }
    }

    public function test_inside_an_outer_transaction_the_failure_propagates_unchanged_without_retry(): void
    {
        Log::spy();
        $attempts = 0;
        $deadlock = FakeDatabaseErrors::deadlock();
        DB::beginTransaction();

        try {
            Transactions::retrying(function () use (&$attempts, $deadlock) {
                $attempts++;

                throw $deadlock;
            });
            $this->fail('Expected the deadlock to propagate.');
        } catch (DeadlockException $exception) {
            // Laravel's nested-transaction handling wrapped it; the helper neither caught nor converted it.
            $this->assertSame($deadlock, $exception->getPrevious());
            $this->assertSame(1, $attempts);
        } finally {
            DB::rollBack();
        }

        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_nested_deadlock_is_retried_by_the_outermost_caller(): void
    {
        Log::spy();
        $commits = $this->countOutermostCommits();
        $outer = 0;
        $seen = [];

        $result = Transactions::retrying(function () use (&$outer, &$seen) {
            $outer++;
            DB::table('h1_probe')->insert(['attempt' => $outer]);

            try {
                // A service called by another service: Laravel's real nested transaction (savepoint).
                Transactions::retrying(function () use ($outer) {
                    DB::table('h1_probe')->insert(['attempt' => $outer * 10]);

                    if ($outer === 1) {
                        throw FakeDatabaseErrors::deadlock();
                    }
                });
            } catch (Throwable $exception) {
                $seen[] = $exception::class;

                throw $exception;
            }

            return 'committed';
        });

        $this->assertSame('committed', $result);
        $this->assertSame(2, $outer);
        $this->assertSame([DeadlockException::class], $seen, 'The inner helper must not convert the deadlock.');
        $this->assertSame(1, $commits->count);
        // Only the committed attempt's writes (outer and nested) exist.
        $this->assertSame([2, 20], DB::table('h1_probe')->orderBy('attempt')->pluck('attempt')->all());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_nested_deadlock_on_every_attempt_ends_as_service_busy_after_three(): void
    {
        Log::shouldReceive('warning')->once()->with('db.service_busy', ['reason' => 'deadlock', 'attempts' => 3]);
        $commits = $this->countOutermostCommits();
        $outer = 0;
        $inner = 0;

        try {
            Transactions::retrying(function () use (&$outer, &$inner) {
                $outer++;
                DB::table('h1_probe')->insert(['attempt' => $outer]);

                Transactions::retrying(function () use (&$inner) {
                    $inner++;

                    throw FakeDatabaseErrors::deadlock();
                });
            });
            $this->fail('Expected ServiceBusyException.');
        } catch (ServiceBusyException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(3, $outer);
        $this->assertSame(3, $inner);
        $this->assertSame(0, $commits->count);
        $this->assertSame(0, DB::table('h1_probe')->count());
    }

    public function test_a_nested_lock_wait_timeout_is_not_retried(): void
    {
        // Laravel also wraps a nested 1205 in a DeadlockException; the chain still says 1205.
        Log::shouldReceive('warning')->once()->with('db.service_busy', ['reason' => 'lock_wait_timeout', 'attempts' => 1]);
        $outer = 0;

        try {
            Transactions::retrying(function () use (&$outer) {
                $outer++;

                Transactions::retrying(fn () => throw FakeDatabaseErrors::lockWaitTimeout());
            });
            $this->fail('Expected ServiceBusyException.');
        } catch (ServiceBusyException) {
            $this->assertSame(1, $outer);
        }
    }

    public function test_a_nested_non_concurrency_failure_propagates_unchanged_without_retry(): void
    {
        Log::spy();

        foreach ([
            new RuntimeException('Fictional failure.'),
            new DomainRuleException(DomainRuleException::ALREADY_ACTIVE),
            FakeDatabaseErrors::uniqueViolation(),
        ] as $error) {
            $outer = 0;
            $inner = 0;

            try {
                Transactions::retrying(function () use (&$outer, &$inner, $error) {
                    $outer++;
                    DB::table('h1_probe')->insert(['attempt' => $outer]);

                    Transactions::retrying(function () use (&$inner, $error) {
                        $inner++;

                        throw $error;
                    });
                });
                $this->fail('Expected the original exception.');
            } catch (Throwable $thrown) {
                $this->assertSame($error, $thrown, $error::class);
                $this->assertSame(1, $outer, $error::class);
                $this->assertSame(1, $inner, $error::class);
            }
        }

        $this->assertSame(0, DB::table('h1_probe')->count());
        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Counts real COMMITs: Laravel fires TransactionCommitting only for the outermost level.
     */
    private function countOutermostCommits(): object
    {
        $commits = new class
        {
            public int $count = 0;
        };

        Event::listen(TransactionCommitting::class, function () use ($commits) {
            $commits->count++;
        });

        return $commits;
    }
}
