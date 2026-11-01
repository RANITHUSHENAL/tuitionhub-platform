<?php

namespace App\Support;

use App\Exceptions\ServiceBusyException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

/**
 * Database transactions with a bounded retry for lost concurrency races (D-022).
 *
 * - Only a MySQL deadlock (SQLSTATE 40001 / error 1213) is retried, at most MAX_ATTEMPTS times in
 *   total. InnoDB has already rolled the whole transaction back, so the callback simply runs again
 *   from the start. Nothing else is ever retried: validation, authorization, business-rule and
 *   unique-key errors are rethrown unchanged on the first attempt.
 * - A lock wait timeout (error 1205) is not retried: the request already waited the full
 *   innodb_lock_wait_timeout.
 * - Exhausted retries and lock wait timeouts become ServiceBusyException (503, Retry-After: 1),
 *   with one db.service_busy warning, only at the outermost level.
 * - Inside an outer transaction (a service called by another service) the callback runs once and
 *   every exception propagates unchanged: no catch, no log, no conversion. A deadlock has already
 *   rolled back the outer transaction too, so only the outermost retrying() can restart it; it
 *   finds the deadlock in the exception chain (Laravel wraps it in a DeadlockException).
 *
 * The callback must have no side effects outside the database (no SMS, mail or HTTP calls):
 * those belong after the transaction has committed. Audit rows are written inside the
 * transaction, so a rolled-back attempt leaves none and only the committed attempt is recorded.
 */
final class Transactions
{
    public const MAX_ATTEMPTS = 3;

    private const MYSQL_DEADLOCK = 1213;

    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;

    private const DEADLOCK = 'deadlock';

    private const LOCK_WAIT_TIMEOUT = 'lock_wait_timeout';

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @throws ServiceBusyException
     */
    public static function retrying(Closure $callback, int $attempts = self::MAX_ATTEMPTS): mixed
    {
        $connection = DB::connection();

        // Nested: the outermost retrying() owns classification, logging and retry.
        if ($connection->transactionLevel() > 0) {
            return $connection->transaction($callback);
        }

        $maxAttempts = max(1, min($attempts, self::MAX_ATTEMPTS));

        for ($attempt = 1; ; $attempt++) {
            try {
                return $connection->transaction($callback);
            } catch (Throwable $exception) {
                $failure = self::concurrencyFailure($exception);

                if ($failure === null) {
                    throw $exception;
                }

                if ($failure === self::DEADLOCK && $attempt < $maxAttempts) {
                    continue;
                }

                // No SQL, bindings or exception message: only what happened and how often.
                Log::warning('db.service_busy', ['reason' => $failure, 'attempts' => $attempt]);

                throw new ServiceBusyException;
            }
        }
    }

    /**
     * Walks the exception chain, because Laravel wraps the PDO error in a QueryException and, in a
     * nested transaction, in a DeadlockException.
     */
    private static function concurrencyFailure(Throwable $exception): ?string
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if (! $current instanceof PDOException || $current->errorInfo === null) {
                continue;
            }

            $sqlState = $current->errorInfo[0] ?? null;
            $driverCode = (int) ($current->errorInfo[1] ?? 0);

            if ($sqlState === '40001' || $driverCode === self::MYSQL_DEADLOCK) {
                return self::DEADLOCK;
            }

            if ($driverCode === self::MYSQL_LOCK_WAIT_TIMEOUT) {
                return self::LOCK_WAIT_TIMEOUT;
            }
        }

        return null;
    }
}
