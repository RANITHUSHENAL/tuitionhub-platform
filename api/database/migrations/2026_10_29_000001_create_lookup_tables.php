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
        // docs/SPRINT_SCHEMA.md G1.1–G1.6. Seeded taxonomy, referenced by code.
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('subjects_code_unique');
            $table->string('name', 150);
            $table->string('name_si', 150)->nullable();
            $table->string('name_ta', 150)->nullable();
            $table->string('category', 32)->default('core');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::statement("ALTER TABLE `subjects` ADD CONSTRAINT `subjects_category_chk` CHECK (`category` IN ('core', 'optional', 'extra'))");

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('grades_code_unique');
            $table->string('name', 150);
            $table->string('level', 40);
            $table->unsignedSmallInteger('sort_order')->default(0)->index('grades_sort_order_index');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::statement("ALTER TABLE `grades` ADD CONSTRAINT `grades_level_chk` CHECK (`level` IN ('primary', 'junior_secondary', 'ol', 'al'))");

        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('streams_code_unique');
            $table->string('name', 150);
            $table->string('level', 40)->default('al');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('exam_targets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('exam_targets_code_unique');
            $table->string('name', 150);
            $table->string('exam', 16);
            $table->unsignedSmallInteger('year');
            $table->date('approximate_exam_date')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::statement("ALTER TABLE `exam_targets` ADD CONSTRAINT `exam_targets_exam_chk` CHECK (`exam` IN ('ol', 'al'))");

        Schema::create('mediums', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique('mediums_code_unique');
            $table->string('name', 100);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('syllabi_code_unique');
            $table->string('name', 120);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabi');
        Schema::dropIfExists('mediums');
        Schema::dropIfExists('exam_targets');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('subjects');
    }
};
