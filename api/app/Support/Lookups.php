<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Resolves taxonomy codes to internal IDs, matching the code exactly (contract §1.5). IDs never
 * leave the server.
 */
final class Lookups
{
    private const TABLES = ['subjects', 'grades', 'streams', 'exam_targets', 'mediums', 'syllabi'];

    public static function id(string $table, string $code): ?int
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException("[{$table}] is not a lookup table.");
        }

        $id = DB::table($table)->whereRaw('`code` COLLATE utf8mb4_bin = ?', [$code])->value('id');

        return $id === null ? null : (int) $id;
    }
}
