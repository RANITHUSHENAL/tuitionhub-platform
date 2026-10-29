<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LookupSeeder (FD-7, Q21; D-020). Runs only against the test database here.
 */
class LookupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_exactly_the_approved_rows_with_utc_timestamps(): void
    {
        $this->seed(LookupSeeder::class);

        foreach (LookupSeeder::tables() as $table => $rows) {
            $this->assertSame(count($rows), DB::table($table)->count(), $table);

            foreach ($rows as $row) {
                $stored = (array) DB::table($table)->where('code', $row['code'])->sole();

                foreach ($row as $column => $value) {
                    $this->assertEquals($value, $stored[$column], "{$table}.{$column} for {$row['code']}");
                }

                $this->assertNotNull($stored['created_at'], $table);
                $this->assertNotNull($stored['updated_at'], $table);
            }
        }

        $this->assertNull(DB::table('subjects')->where('code', 'MATH')->value('name_si'));
        $this->assertSame(15, DB::table('subjects')->count());
        $this->assertSame(
            ['ACC', 'BIO', 'BUS', 'CHEM', 'COMBINED_MATH', 'ECON', 'ENG', 'GEO', 'HIS', 'ICT', 'MATH', 'PHY', 'SCI', 'SIN', 'TAM'],
            DB::table('subjects')->orderBy('code')->pluck('code')->all(),
        );
        $this->assertSame('Combined Mathematics', DB::table('subjects')->where('code', 'COMBINED_MATH')->value('name'));
        $this->assertSame(['G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'AL1', 'AL2'], DB::table('grades')->orderBy('sort_order')->pluck('code')->all());
    }

    public function test_a_second_run_writes_nothing(): void
    {
        $this->seed(LookupSeeder::class);
        $before = $this->snapshot();

        $this->travel(5)->minutes();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->seed(LookupSeeder::class);

        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b/i', $query['query']);
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_changed_value_is_corrected_without_changing_id_or_created_at_and_nothing_is_deleted(): void
    {
        $this->seed(LookupSeeder::class);
        $original = DB::table('grades')->where('code', 'G11')->sole();
        DB::table('grades')->where('code', 'G11')->update(['name' => 'Wrong Name']);
        // A row that is not in the seed list (for example, referenced by existing data) is kept.
        DB::table('grades')->insert(['code' => 'G5', 'name' => 'Grade 5', 'level' => 'primary', 'sort_order' => 5, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);

        $this->travel(5)->minutes();
        $this->seed(LookupSeeder::class);

        $corrected = DB::table('grades')->where('code', 'G11')->sole();
        $this->assertSame('Grade 11', $corrected->name);
        $this->assertSame($original->id, $corrected->id);
        $this->assertSame($original->created_at, $corrected->created_at);
        $this->assertNotSame($original->updated_at, $corrected->updated_at);
        $this->assertTrue(DB::table('grades')->where('code', 'G5')->exists());
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $snapshot = [];

        foreach (array_keys(LookupSeeder::tables()) as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
        }

        return $snapshot;
    }
}
