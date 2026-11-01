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
        // docs/SPRINT_SCHEMA.md G5.2 (D-024). Append-only: only effective_to is ever set, once.
        Schema::create('class_fee_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('LKR');
            $table->string('billing_cycle', 32);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // 1 while open (no effective_to), NULL once closed: one open version per class.
            $table->unsignedTinyInteger('current_flag')->nullable()->storedAs('IF(`effective_to` IS NULL, 1, NULL)');
            $table->unsignedBigInteger('created_by_user_id');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['class_id', 'current_flag'], 'class_fee_versions_current_unique');
            $table->unique(['class_id', 'effective_from'], 'class_fee_versions_class_from_unique');
            $table->foreign('class_id', 'class_fee_versions_class_fk')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'class_fee_versions_created_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `class_fee_versions` ADD CONSTRAINT `class_fee_versions_amount_chk` CHECK (`amount` > 0)');
        DB::statement("ALTER TABLE `class_fee_versions` ADD CONSTRAINT `class_fee_versions_currency_chk` CHECK (`currency` = 'LKR')");
        DB::statement("ALTER TABLE `class_fee_versions` ADD CONSTRAINT `class_fee_versions_cycle_chk` CHECK (`billing_cycle` IN ('monthly', 'term', 'one_time'))");
        DB::statement('ALTER TABLE `class_fee_versions` ADD CONSTRAINT `class_fee_versions_dates_chk` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_fee_versions');
    }
};
