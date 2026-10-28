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
        // docs/SPRINT_SCHEMA.md G2.4. A profile grants no institute access by itself.
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('teacher_profiles_uuid_unique');
            $table->unsignedBigInteger('user_id')->unique('teacher_profiles_user_id_unique');
            $table->string('display_name');
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->boolean('is_independent')->default(false);
            $table->string('public_visibility', 32)->default('institute_attributed');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('user_id', 'teacher_profiles_user_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE `teacher_profiles` ADD CONSTRAINT `teacher_profiles_visibility_chk` CHECK (`public_visibility` IN ('full', 'institute_attributed', 'hidden'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};
