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
        // docs/SPRINT_SCHEMA.md G5.4 (D-024). Times are Asia/Colombo wall-clock times, whole minutes.
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('class_sessions_uuid_unique');
            $table->unsignedBigInteger('class_id');
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 32)->default('scheduled');
            // 1 unless cancelled: a cancelled session keeps its row but frees its slot (contract v0.7.1 §10.1).
            $table->unsignedTinyInteger('slot_flag')->nullable()->storedAs("IF(`status` <> 'cancelled', 1, NULL)");
            $table->unsignedBigInteger('created_by_user_id');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['class_id', 'session_date', 'start_time', 'slot_flag'], 'class_sessions_slot_unique');
            // Parent key for the attendances (session_id, class_id) composite FK.
            $table->unique(['id', 'class_id'], 'class_sessions_id_class_unique');
            $table->foreign('class_id', 'class_sessions_class_fk')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'class_sessions_created_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `class_sessions` ADD CONSTRAINT `class_sessions_status_chk` CHECK (`status` IN ('scheduled', 'cancelled'))");
        DB::statement('ALTER TABLE `class_sessions` ADD CONSTRAINT `class_sessions_times_chk` CHECK (`end_time` > `start_time`)');
        DB::statement('ALTER TABLE `class_sessions` ADD CONSTRAINT `class_sessions_minutes_chk` CHECK (SECOND(`start_time`) = 0 AND SECOND(`end_time`) = 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
