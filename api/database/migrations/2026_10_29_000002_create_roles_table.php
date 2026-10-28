<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // docs/SPRINT_SCHEMA.md G1.7. Only the three stored roles exist (D-011).
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique('roles_name_unique');
            // Must match user_roles.role_scope exactly for the composite FK.
            $table->string('scope', 32)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['id', 'scope'], 'roles_id_scope_unique');
        });

        DB::statement("ALTER TABLE `roles` ADD CONSTRAINT `roles_scope_chk` CHECK (`scope` IN ('global', 'institute'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `roles` ADD CONSTRAINT `roles_name_scope_chk`
                CHECK ((`name` = 'super_admin' AND `scope` = 'global')
                    OR (`name` IN ('institute_admin', 'institute_staff') AND `scope` = 'institute'))
            SQL);

        // Query-builder inserts do not fill timestamps, so they are set here in UTC.
        $now = now('UTC');

        DB::table('roles')->insert([
            ['name' => 'super_admin', 'scope' => 'global', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'institute_admin', 'scope' => 'institute', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'institute_staff', 'scope' => 'institute', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
