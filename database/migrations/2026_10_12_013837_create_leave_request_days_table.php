<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each day (or half day) a leave application covers, and whether it is paid.
 * Used to excuse the day on the DTR, to find overlapping applications, and
 * to count leave without pay for leave credits and payroll.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_request_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('portion', 4);
            $table->decimal('days', 4, 3);
            $table->decimal('days_with_pay', 4, 3)->default(0);
            $table->timestamps();

            $table->unique(['leave_request_id', 'date']);
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request_days');
    }
};
