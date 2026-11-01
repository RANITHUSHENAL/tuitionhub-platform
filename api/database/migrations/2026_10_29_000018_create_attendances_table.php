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
        // docs/SPRINT_SCHEMA.md G6.1 (D-024). One mark per session and enrolment; corrections update in place.
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('enrollment_id');
            // Cross-class invariant: the session's class and the enrolment's class (two composite FKs).
            $table->unsignedBigInteger('class_id');
            $table->string('status', 32);
            $table->string('method', 32)->default('manual');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('marked_by_user_id');
            $table->dateTime('marked_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['session_id', 'enrollment_id'], 'attendances_session_enrollment_unique');
            $table->index(['session_id', 'class_id'], 'attendances_session_class_index');
            $table->index(['enrollment_id', 'class_id'], 'attendances_enrollment_class_index');
            // The session and the enrolment must belong to the same class.
            $table->foreign(['session_id', 'class_id'], 'attendances_session_class_fk')
                ->references(['id', 'class_id'])->on('class_sessions')->restrictOnDelete();
            $table->foreign(['enrollment_id', 'class_id'], 'attendances_enrollment_class_fk')
                ->references(['id', 'class_id'])->on('class_enrollments')->restrictOnDelete();
            $table->foreign('marked_by_user_id', 'attendances_marked_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `attendances` ADD CONSTRAINT `attendances_status_chk` CHECK (`status` IN ('present', 'absent', 'late', 'excused'))");
        DB::statement("ALTER TABLE `attendances` ADD CONSTRAINT `attendances_method_chk` CHECK (`method` IN ('manual'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
