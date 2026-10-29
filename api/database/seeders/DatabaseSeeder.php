<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data only (D-020). Running it against any database needs separate approval.
        $this->call(LookupSeeder::class);

        // No default user. Fictional demo data is added by a separately approved task.
    }
}
