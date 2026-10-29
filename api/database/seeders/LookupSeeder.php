<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Lookup reference data approved as FD-7 (Q21; D-020): standard Sri Lankan taxonomy, no personal
 * data. Sinhala and Tamil names stay NULL until the founders supply them, and exam dates stay
 * NULL rather than being guessed.
 *
 * Idempotent: rows are matched on `code`; only new or changed rows are written (with explicit UTC
 * timestamps, SPRINT_SCHEMA §0.A), `created_at` is never overwritten, IDs never change, and
 * nothing is ever deleted (existing rows may be referenced). A second run writes nothing.
 *
 * Before writing, each table is checked to have `code` as its only unique key besides the primary
 * key, because MySQL's upsert (ON DUPLICATE KEY UPDATE) matches on every unique key.
 */
class LookupSeeder extends Seeder
{
    public const SUBJECTS = [
        ['code' => 'MATH', 'name' => 'Mathematics', 'category' => 'core'],
        ['code' => 'COMBINED_MATH', 'name' => 'Combined Mathematics', 'category' => 'core'],
        ['code' => 'SCI', 'name' => 'Science', 'category' => 'core'],
        ['code' => 'PHY', 'name' => 'Physics', 'category' => 'core'],
        ['code' => 'CHEM', 'name' => 'Chemistry', 'category' => 'core'],
        ['code' => 'BIO', 'name' => 'Biology', 'category' => 'core'],
        ['code' => 'ICT', 'name' => 'Information and Communication Technology', 'category' => 'core'],
        ['code' => 'ENG', 'name' => 'English', 'category' => 'core'],
        ['code' => 'SIN', 'name' => 'Sinhala', 'category' => 'core'],
        ['code' => 'TAM', 'name' => 'Tamil', 'category' => 'core'],
        ['code' => 'HIS', 'name' => 'History', 'category' => 'core'],
        ['code' => 'GEO', 'name' => 'Geography', 'category' => 'core'],
        ['code' => 'ACC', 'name' => 'Accounting', 'category' => 'core'],
        ['code' => 'BUS', 'name' => 'Business Studies', 'category' => 'core'],
        ['code' => 'ECON', 'name' => 'Economics', 'category' => 'core'],
    ];

    public const GRADES = [
        ['code' => 'G6', 'name' => 'Grade 6', 'level' => 'junior_secondary', 'sort_order' => 6],
        ['code' => 'G7', 'name' => 'Grade 7', 'level' => 'junior_secondary', 'sort_order' => 7],
        ['code' => 'G8', 'name' => 'Grade 8', 'level' => 'junior_secondary', 'sort_order' => 8],
        ['code' => 'G9', 'name' => 'Grade 9', 'level' => 'junior_secondary', 'sort_order' => 9],
        ['code' => 'G10', 'name' => 'Grade 10', 'level' => 'ol', 'sort_order' => 10],
        ['code' => 'G11', 'name' => 'Grade 11', 'level' => 'ol', 'sort_order' => 11],
        ['code' => 'AL1', 'name' => 'A/L Year 1 (Grade 12)', 'level' => 'al', 'sort_order' => 12],
        ['code' => 'AL2', 'name' => 'A/L Year 2 (Grade 13)', 'level' => 'al', 'sort_order' => 13],
    ];

    public const STREAMS = [
        ['code' => 'SCI_MATH', 'name' => 'Physical Science', 'level' => 'al'],
        ['code' => 'SCI_BIO', 'name' => 'Biological Science', 'level' => 'al'],
        ['code' => 'COMMERCE', 'name' => 'Commerce', 'level' => 'al'],
        ['code' => 'ARTS', 'name' => 'Arts', 'level' => 'al'],
        ['code' => 'TECH', 'name' => 'Technology', 'level' => 'al'],
    ];

    public const EXAM_TARGETS = [
        ['code' => 'OL_2026', 'name' => 'O/L 2026', 'exam' => 'ol', 'year' => 2026, 'approximate_exam_date' => null],
        ['code' => 'OL_2027', 'name' => 'O/L 2027', 'exam' => 'ol', 'year' => 2027, 'approximate_exam_date' => null],
        ['code' => 'OL_2028', 'name' => 'O/L 2028', 'exam' => 'ol', 'year' => 2028, 'approximate_exam_date' => null],
        ['code' => 'AL_2026', 'name' => 'A/L 2026', 'exam' => 'al', 'year' => 2026, 'approximate_exam_date' => null],
        ['code' => 'AL_2027', 'name' => 'A/L 2027', 'exam' => 'al', 'year' => 2027, 'approximate_exam_date' => null],
        ['code' => 'AL_2028', 'name' => 'A/L 2028', 'exam' => 'al', 'year' => 2028, 'approximate_exam_date' => null],
    ];

    public const MEDIUMS = [
        ['code' => 'sin', 'name' => 'Sinhala'],
        ['code' => 'tam', 'name' => 'Tamil'],
        ['code' => 'eng', 'name' => 'English'],
    ];

    public const SYLLABI = [
        ['code' => 'local', 'name' => 'Local (National)'],
        ['code' => 'edexcel', 'name' => 'Pearson Edexcel'],
        ['code' => 'cambridge', 'name' => 'Cambridge International'],
    ];

    /**
     * Insert order. The six tables have no foreign keys between them; the order is for readability.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function tables(): array
    {
        return [
            'subjects' => self::SUBJECTS,
            'grades' => self::GRADES,
            'streams' => self::STREAMS,
            'exam_targets' => self::EXAM_TARGETS,
            'mediums' => self::MEDIUMS,
            'syllabi' => self::SYLLABI,
        ];
    }

    public function run(): void
    {
        foreach (self::tables() as $table => $rows) {
            $this->assertCodeIsTheOnlyUniqueKey($table);

            DB::transaction(fn () => $this->seed($table, $rows));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seed(string $table, array $rows): void
    {
        $existing = DB::table($table)
            ->whereIn('code', array_column($rows, 'code'))
            ->lockForUpdate()
            ->get()
            ->keyBy('code');

        $now = now('UTC');
        $pending = [];

        foreach ($rows as $row) {
            $current = $existing->get($row['code']);

            if ($current !== null && $this->matches($current, $row)) {
                continue;
            }

            $pending[] = [...$row, 'created_at' => $now, 'updated_at' => $now];
        }

        if ($pending === []) {
            return;
        }

        $columns = array_keys(self::tables()[$table][0]);

        // created_at is set on insert only; an update changes the values and updated_at.
        DB::table($table)->upsert($pending, ['code'], [...array_diff($columns, ['code']), 'updated_at']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matches(object $current, array $row): bool
    {
        foreach ($row as $column => $value) {
            // Codes compare case-insensitively in MySQL, but the stored spelling must be exact.
            if ((string) $current->{$column} !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function assertCodeIsTheOnlyUniqueKey(string $table): void
    {
        $unique = array_values(array_filter(
            Schema::getIndexes($table),
            fn (array $index) => $index['unique'] && ! $index['primary'],
        ));

        if (count($unique) !== 1 || $unique[0]['columns'] !== ['code']) {
            throw new RuntimeException("LookupSeeder: [{$table}] must have exactly one unique key, on [code].");
        }
    }
}
