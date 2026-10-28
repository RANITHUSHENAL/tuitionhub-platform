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
        // docs/SPRINT_SCHEMA.md G4.2. Only the HMAC of the code is stored (contract v0.3 §9.1).
        Schema::create('guardian_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('guardian_invitations_uuid_unique');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->string('guardian_phone', 20);
            $table->string('relationship', 32);
            $table->char('code_hash', 64)->unique('guardian_invitations_code_hash_unique');
            $table->string('status', 32)->default('pending');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by_user_id')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            // 1 while pending, NULL otherwise: one pending invitation per student and phone.
            $table->unsignedTinyInteger('pending_flag')->nullable()->storedAs("IF(`status` = 'pending', 1, NULL)");
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['student_id', 'guardian_phone', 'pending_flag'], 'guardian_invitations_pending_unique');
            // Parent key for the student_guardians (invitation_id, student_id) composite FK.
            $table->unique(['id', 'student_id'], 'guardian_invitations_id_student_unique');
            $table->index(['student_id', 'institute_id'], 'guardian_invitations_student_institute_index');
            $table->index(['status', 'expires_at'], 'guardian_invitations_status_expires_index');
            // The invitation's institute must be the student's managing institute.
            $table->foreign(['student_id', 'institute_id'], 'guardian_invitations_student_institute_fk')
                ->references(['id', 'managing_institute_id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign('invited_by_user_id', 'guardian_invitations_invited_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('accepted_by_user_id', 'guardian_invitations_accepted_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by_user_id', 'guardian_invitations_revoked_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_status_chk` CHECK (`status` IN ('pending', 'accepted', 'revoked', 'expired'))");
        DB::statement("ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_relationship_chk` CHECK (`relationship` IN ('father', 'mother', 'guardian', 'uncle', 'aunt'))");
        DB::statement(<<<'SQL'
            ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_phone_format_chk`
                CHECK (REGEXP_LIKE(`guardian_phone`, '^[+]947[0-9]{8}$', 'c'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_accepted_chk`
                CHECK ((`status` = 'accepted' AND `accepted_at` IS NOT NULL AND `accepted_by_user_id` IS NOT NULL)
                    OR (`status` <> 'accepted' AND `accepted_at` IS NULL AND `accepted_by_user_id` IS NULL))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_revoked_chk`
                CHECK ((`status` = 'revoked' AND `revoked_at` IS NOT NULL AND `revoked_by_user_id` IS NOT NULL)
                    OR (`status` <> 'revoked' AND `revoked_at` IS NULL AND `revoked_by_user_id` IS NULL))
            SQL);
        DB::statement('ALTER TABLE `guardian_invitations` ADD CONSTRAINT `guardian_invitations_accept_before_expiry_chk` CHECK (`accepted_at` IS NULL OR `accepted_at` <= `expires_at`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_invitations');
    }
};
