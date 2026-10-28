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
        // docs/SPRINT_SCHEMA.md G2.2. Current-state grants; RoleAssignmentService is the only writer.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            // Must match roles.scope exactly for the composite FK.
            $table->string('role_scope', 32)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->unsignedBigInteger('institute_id')->nullable();
            // 0 is never a real institute id, so one global row per user and role.
            $table->unsignedBigInteger('scope_key')->nullable(false)->storedAs('IFNULL(`institute_id`, 0)');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'role_id', 'scope_key'], 'user_roles_user_role_scope_unique');
            $table->index(['institute_id', 'role_id'], 'user_roles_institute_role_index');
            $table->foreign('user_id', 'user_roles_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('institute_id', 'user_roles_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
            $table->foreign(['role_id', 'role_scope'], 'user_roles_role_scope_fk')->references(['id', 'scope'])->on('roles')->restrictOnDelete();
        });

        // The FK pins role_scope to the role's real scope; this CHECK ties the scope to institute_id.
        DB::statement(<<<'SQL'
            ALTER TABLE `user_roles` ADD CONSTRAINT `user_roles_scope_chk`
                CHECK ((`role_scope` = 'global' AND `institute_id` IS NULL)
                    OR (`role_scope` = 'institute' AND `institute_id` IS NOT NULL))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
