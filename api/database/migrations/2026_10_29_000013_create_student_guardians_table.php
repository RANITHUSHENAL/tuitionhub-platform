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
        // docs/SPRINT_SCHEMA.md G4.3. Links are never deleted or reactivated; re-linking adds a new row.
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('guardian_id');
            $table->string('relationship', 32);
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('linked_by_user_id');
            $table->dateTime('linked_at');
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable()->unique('student_guardians_invitation_unique');
            // 1 while active, NULL once revoked: one active link per student and guardian.
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs('IF(`revoked_at` IS NULL, 1, NULL)');
            // 1 for the active primary link, NULL otherwise: at most one active primary per student.
            $table->unsignedTinyInteger('primary_flag')->nullable()->storedAs('IF(`is_primary` = 1 AND `revoked_at` IS NULL, 1, NULL)');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['student_id', 'guardian_id', 'active_flag'], 'student_guardians_active_unique');
            $table->unique(['student_id', 'primary_flag'], 'student_guardians_primary_unique');
            $table->index(['guardian_id', 'active_flag'], 'student_guardians_guardian_active_index');
            $table->index(['invitation_id', 'student_id'], 'student_guardians_invitation_student_index');
            $table->foreign('student_id', 'student_guardians_student_fk')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('guardian_id', 'student_guardians_guardian_fk')->references('id')->on('guardian_profiles')->restrictOnDelete();
            $table->foreign('linked_by_user_id', 'student_guardians_linked_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by_user_id', 'student_guardians_revoked_by_fk')->references('id')->on('users')->restrictOnDelete();
            // A link that names an invitation must belong to that invitation's student.
            // A NULL invitation_id is not checked (MySQL MATCH SIMPLE semantics).
            $table->foreign(['invitation_id', 'student_id'], 'student_guardians_invitation_student_fk')
                ->references(['id', 'student_id'])->on('guardian_invitations')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `student_guardians` ADD CONSTRAINT `student_guardians_relationship_chk` CHECK (`relationship` IN ('father', 'mother', 'guardian', 'uncle', 'aunt'))");
        DB::statement('ALTER TABLE `student_guardians` ADD CONSTRAINT `student_guardians_is_primary_chk` CHECK (`is_primary` IN (0, 1))');
        DB::statement(<<<'SQL'
            ALTER TABLE `student_guardians` ADD CONSTRAINT `student_guardians_revocation_chk`
                CHECK ((`revoked_at` IS NULL AND `revoked_by_user_id` IS NULL)
                    OR (`revoked_at` IS NOT NULL AND `revoked_by_user_id` IS NOT NULL))
            SQL);
        DB::statement('ALTER TABLE `student_guardians` ADD CONSTRAINT `student_guardians_revoked_after_linked_chk` CHECK (`revoked_at` IS NULL OR `revoked_at` >= `linked_at`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
