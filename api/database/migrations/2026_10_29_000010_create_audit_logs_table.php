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
        // docs/SPRINT_SCHEMA.md R2.0. Append-only, enforced by the application (D-015 Q33):
        // no updated_at or deleted_at, and created_at is always set by the application in UTC.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('action', 64);
            $table->string('auditable_type', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->dateTime('created_at');

            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
            $table->index(['institute_id', 'created_at'], 'audit_logs_institute_created_index');
            $table->foreign('user_id', 'audit_logs_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('institute_id', 'audit_logs_institute_fk')->references('id')->on('institutes')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
