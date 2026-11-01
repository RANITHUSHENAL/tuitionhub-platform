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
        // docs/SPRINT_SCHEMA.md G5.3 (D-024). Never deleted or reactivated; re-enrolling adds a row.
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('class_enrollments_uuid_unique');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            // Tenant invariant: the class's institute and the student's managing institute (two composite FKs).
            $table->unsignedBigInteger('institute_id');
            $table->string('status', 32)->default('active');
            $table->date('enrolled_on');
            $table->date('withdrawn_on')->nullable();
            // 1 while active, NULL once withdrawn: one active enrolment per class and student, any history.
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs("IF(`status` = 'active', 1, NULL)");
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['class_id', 'student_id', 'active_flag'], 'class_enrollments_active_unique');
            // Parent key for the attendances (enrollment_id, class_id) composite FK.
            $table->unique(['id', 'class_id'], 'class_enrollments_id_class_unique');
            $table->index(['class_id', 'institute_id'], 'class_enrollments_class_institute_index');
            $table->index(['student_id', 'institute_id'], 'class_enrollments_student_institute_index');
            // The class and the student must belong to the same institute.
            $table->foreign(['class_id', 'institute_id'], 'class_enrollments_class_institute_fk')
                ->references(['id', 'institute_id'])->on('classes')->restrictOnDelete();
            $table->foreign(['student_id', 'institute_id'], 'class_enrollments_student_institute_fk')
                ->references(['id', 'managing_institute_id'])->on('student_profiles')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `class_enrollments` ADD CONSTRAINT `class_enrollments_status_chk` CHECK (`status` IN ('active', 'withdrawn'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `class_enrollments` ADD CONSTRAINT `class_enrollments_withdrawal_chk`
                CHECK ((`status` = 'active' AND `withdrawn_on` IS NULL)
                    OR (`status` = 'withdrawn' AND `withdrawn_on` IS NOT NULL))
            SQL);
        // withdrawn_on = enrolled_on is a zero-length enrolment, never active (D-024).
        DB::statement('ALTER TABLE `class_enrollments` ADD CONSTRAINT `class_enrollments_dates_chk` CHECK (`withdrawn_on` IS NULL OR `withdrawn_on` >= `enrolled_on`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};
