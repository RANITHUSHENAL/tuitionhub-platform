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
        // docs/SPRINT_SCHEMA.md G2.1. Phone-only OTP login: no password reset table.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('users_uuid_unique');
            $table->string('name')->nullable();
            $table->string('phone', 20)->unique('users_phone_unique');
            $table->string('email')->nullable()->unique('users_email_unique');
            $table->string('password')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->string('status', 32)->default('active')->index('users_status_index');
            $table->rememberToken();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_status_chk` CHECK (`status` IN ('active', 'suspended'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `users` ADD CONSTRAINT `users_phone_format_chk`
                CHECK (REGEXP_LIKE(`phone`, '^[+]947[0-9]{8}$', 'c'))
            SQL);

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
