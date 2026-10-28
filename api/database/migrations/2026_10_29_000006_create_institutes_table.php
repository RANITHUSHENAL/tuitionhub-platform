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
        // docs/SPRINT_SCHEMA.md G3.1. Disabled via status; never deleted in the sprint.
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('institutes_uuid_unique');
            $table->unsignedBigInteger('owner_user_id')->index('institutes_owner_index');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active')->index('institutes_status_index');
            $table->string('district', 100);
            $table->string('city', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('business_reg_no', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['district', 'status'], 'institutes_district_status_index');
            $table->foreign('owner_user_id', 'institutes_owner_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `institutes` ADD CONSTRAINT `institutes_status_chk` CHECK (`status` IN ('active', 'suspended'))");
        // The 25 districts of Sri Lanka. The collation is case-insensitive, so the
        // server must still store the canonical spelling.
        DB::statement(<<<'SQL'
            ALTER TABLE `institutes` ADD CONSTRAINT `institutes_district_chk`
                CHECK (`district` IN (
                    'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo',
                    'Galle', 'Gampaha', 'Hambantota', 'Jaffna', 'Kalutara',
                    'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala', 'Mannar',
                    'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya',
                    'Polonnaruwa', 'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya'
                ))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutes');
    }
};
