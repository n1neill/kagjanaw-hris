<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per employee per day (CSC Form No. 48), computed from raw logs.
 * Rows HR has corrected are marked adjusted and never recomputed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_time_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->time('am_in')->nullable();
            $table->time('am_out')->nullable();
            $table->time('pm_in')->nullable();
            $table->time('pm_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('undertime_minutes')->default(0);
            $table->unsignedSmallInteger('absent_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->string('status', 20);
            $table->boolean('is_adjusted')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_records');
    }
};
