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
        // docs/SPRINT_SCHEMA.md G5.1 (D-024). No hall, slug or soft delete; retired with status = archived.
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('classes_uuid_unique');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->unsignedBigInteger('exam_target_id');
            $table->unsignedBigInteger('medium_id');
            $table->unsignedBigInteger('syllabus_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mode', 32);
            $table->unsignedInteger('max_students');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('public_listing')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Parent key for the class_enrollments (class_id, institute_id) composite FK.
            $table->unique(['id', 'institute_id'], 'classes_id_institute_unique');
            $table->index(['institute_id', 'status'], 'classes_institute_status_index');
            $table->index(['teacher_id', 'status'], 'classes_teacher_status_index');
            $table->index(['status', 'public_listing', 'subject_id', 'grade_id'], 'classes_catalogue_index');
            $table->foreign('institute_id', 'classes_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
            $table->foreign('teacher_id', 'classes_teacher_fk')->references('id')->on('teacher_profiles')->restrictOnDelete();
            $table->foreign('subject_id', 'classes_subject_fk')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('grade_id', 'classes_grade_fk')->references('id')->on('grades')->restrictOnDelete();
            $table->foreign('stream_id', 'classes_stream_fk')->references('id')->on('streams')->restrictOnDelete();
            $table->foreign('exam_target_id', 'classes_exam_target_fk')->references('id')->on('exam_targets')->restrictOnDelete();
            $table->foreign('medium_id', 'classes_medium_fk')->references('id')->on('mediums')->restrictOnDelete();
            $table->foreign('syllabus_id', 'classes_syllabus_fk')->references('id')->on('syllabi')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `classes` ADD CONSTRAINT `classes_status_chk` CHECK (`status` IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE `classes` ADD CONSTRAINT `classes_mode_chk` CHECK (`mode` IN ('in_person', 'online', 'hybrid'))");
        DB::statement('ALTER TABLE `classes` ADD CONSTRAINT `classes_max_students_chk` CHECK (`max_students` >= 1)');
        DB::statement('ALTER TABLE `classes` ADD CONSTRAINT `classes_dates_chk` CHECK (`ends_on` IS NULL OR `ends_on` >= `starts_on`)');
        DB::statement('ALTER TABLE `classes` ADD CONSTRAINT `classes_public_listing_chk` CHECK (`public_listing` IN (0, 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
