<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave types under the CSC Omnibus Rules on Leave and special laws, with
 * the rules HR can adjust when regulations change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('credit_source', 20);
            $table->decimal('annual_days', 6, 3)->nullable();
            $table->decimal('max_days_per_request', 6, 3)->nullable();
            $table->boolean('counts_calendar_days')->default(false);
            $table->boolean('allows_half_day')->default(true);
            $table->unsignedSmallInteger('advance_notice_days')->default(0);
            $table->boolean('allows_past_dates')->default(false);
            $table->string('eligible_sex', 10)->nullable();
            $table->unsignedSmallInteger('attachment_required_over_days')->nullable();
            $table->string('attachment_hint')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
