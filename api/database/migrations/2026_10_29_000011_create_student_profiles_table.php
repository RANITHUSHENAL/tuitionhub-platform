<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // docs/SPRINT_SCHEMA.md G4.1. Institute-managed student records; fictional data only (C6).
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('student_profiles_uuid_unique');
            $table->unsignedBigInteger('managing_institute_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('user_id')->nullable()->unique('student_profiles_user_id_unique');
            $table->string('full_name');
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('medium_id');
            $table->unsignedBigInteger('exam_target_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Parent key for the guardian_invitations (student_id, institute_id) composite FK.
            $table->unique(['id', 'managing_institute_id'], 'student_profiles_id_institute_unique');
            $table->index(['managing_institute_id', 'grade_id'], 'student_profiles_institute_grade_index');
            $table->foreign('managing_institute_id', 'student_profiles_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'student_profiles_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('user_id', 'student_profiles_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('grade_id', 'student_profiles_grade_fk')->references('id')->on('grades')->restrictOnDelete();
            $table->foreign('medium_id', 'student_profiles_medium_fk')->references('id')->on('mediums')->restrictOnDelete();
            $table->foreign('exam_target_id', 'student_profiles_exam_target_fk')->references('id')->on('exam_targets')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
