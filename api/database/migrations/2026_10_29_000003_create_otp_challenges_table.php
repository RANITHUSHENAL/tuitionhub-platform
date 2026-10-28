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
        // docs/SPRINT_SCHEMA.md G2.3. The code is stored as a hash only.
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('otp_challenges_uuid_unique');
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->string('purpose', 32);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts');
            $table->dateTime('expires_at')->index('otp_challenges_expires_at_index');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('last_sent_at');
            $table->char('request_ip_hash', 64)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['phone', 'purpose', 'created_at'], 'otp_challenges_phone_purpose_created_index');
        });

        DB::statement("ALTER TABLE `otp_challenges` ADD CONSTRAINT `otp_challenges_purpose_chk` CHECK (`purpose` IN ('login'))");
        DB::statement('ALTER TABLE `otp_challenges` ADD CONSTRAINT `otp_challenges_attempts_chk` CHECK (`max_attempts` >= 1 AND `attempts` <= `max_attempts`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
