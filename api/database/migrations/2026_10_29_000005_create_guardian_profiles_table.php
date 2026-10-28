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
        // docs/SPRINT_SCHEMA.md G2.5. Guardian access comes from active links, never from this row.
        Schema::create('guardian_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('guardian_profiles_uuid_unique');
            $table->unsignedBigInteger('user_id')->unique('guardian_profiles_user_id_unique');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('user_id', 'guardian_profiles_user_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_profiles');
    }
};
