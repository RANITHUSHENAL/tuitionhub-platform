<?php

namespace Tests\Support;

use Illuminate\Database\QueryException;
use PDOException;

/**
 * Query exceptions shaped exactly like the ones Laravel builds from MySQL errors, for testing the
 * retry policy without having to provoke a real deadlock (D-022).
 */
final class FakeDatabaseErrors
{
    public static function deadlock(): QueryException
    {
        return self::make('40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction');
    }

    public static function lockWaitTimeout(): QueryException
    {
        return self::make('HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction');
    }

    public static function uniqueViolation(): QueryException
    {
        return self::make('23000', 1062, "Duplicate entry '1' for key 'fake.fake_unique'");
    }

    private static function make(string $sqlState, int $driverCode, string $message): QueryException
    {
        $pdo = new PDOException("SQLSTATE[{$sqlState}]: General error: {$driverCode} {$message}");
        $pdo->errorInfo = [$sqlState, $driverCode, $message];

        return new QueryException('mysql', 'update `fake` set `value` = ?', ['fake-binding'], $pdo, [], null, true);
    }
}
