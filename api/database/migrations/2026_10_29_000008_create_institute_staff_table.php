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
        // docs/SPRINT_SCHEMA.md G3.2. History is kept as former rows; never deleted.
        Schema::create('institute_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('user_id');
            $table->string('position', 32);
            $table->string('status', 32)->default('active');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            // 1 while active, NULL otherwise: one active row per user and institute.
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs("IF(`status` = 'active' AND `left_on` IS NULL, 1, NULL)");
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['institute_id', 'user_id', 'active_flag'], 'institute_staff_active_unique');
            $table->index(['user_id', 'status'], 'institute_staff_user_status_index');
            $table->foreign('institute_id', 'institute_staff_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
            $table->foreign('user_id', 'institute_staff_user_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `institute_staff` ADD CONSTRAINT `institute_staff_status_chk` CHECK (`status` IN ('active', 'former'))");
        DB::statement("ALTER TABLE `institute_staff` ADD CONSTRAINT `institute_staff_position_chk` CHECK (`position` IN ('manager', 'clerk', 'receptionist'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `institute_staff` ADD CONSTRAINT `institute_staff_status_left_chk`
                CHECK ((`status` = 'active' AND `left_on` IS NULL)
                    OR (`status` = 'former' AND `left_on` IS NOT NULL))
            SQL);
        DB::statement('ALTER TABLE `institute_staff` ADD CONSTRAINT `institute_staff_dates_chk` CHECK (`left_on` IS NULL OR `left_on` >= `joined_on`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institute_staff');
    }
};
