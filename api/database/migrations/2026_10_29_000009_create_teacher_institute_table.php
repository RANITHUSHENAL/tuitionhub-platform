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
        // docs/SPRINT_SCHEMA.md G3.3. teacher_id references teacher_profiles (contract §3, §6.2).
        Schema::create('teacher_institute', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('teacher_institute_uuid_unique');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('institute_id');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('status', 32)->default('active');
            // 1 while active, NULL otherwise: one active assignment per teacher and institute.
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs("IF(`status` = 'active' AND `left_on` IS NULL, 1, NULL)");
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['teacher_id', 'institute_id', 'active_flag'], 'teacher_institute_active_unique');
            $table->index(['institute_id', 'status'], 'teacher_institute_institute_status_index');
            $table->foreign('teacher_id', 'teacher_institute_teacher_fk')->references('id')->on('teacher_profiles')->restrictOnDelete();
            $table->foreign('institute_id', 'teacher_institute_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `teacher_institute` ADD CONSTRAINT `teacher_institute_status_chk` CHECK (`status` IN ('active', 'former'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `teacher_institute` ADD CONSTRAINT `teacher_institute_status_left_chk`
                CHECK ((`status` = 'active' AND `left_on` IS NULL)
                    OR (`status` = 'former' AND `left_on` IS NOT NULL))
            SQL);
        DB::statement('ALTER TABLE `teacher_institute` ADD CONSTRAINT `teacher_institute_dates_chk` CHECK (`left_on` IS NULL OR `left_on` >= `joined_on`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_institute');
    }
};
