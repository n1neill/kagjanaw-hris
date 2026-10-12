<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absences and tardiness without pay deducted in a payroll, per month of
 * attendance. A month is deducted after its DTR is validated; if it is
 * corrected later, the next payroll deducts (or refunds) only the difference.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_attendance_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('month')->comment('First day of the attendance month');
            $table->decimal('days_without_pay', 8, 3)->default(0)->comment('Leave without pay and tardiness beyond vacation leave');
            $table->unsignedInteger('absent_minutes')->default(0)->comment('Unexcused absences on the DTR');
            $table->decimal('salary_amount', 12, 2);
            $table->decimal('allowance_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_deductions');
    }
};
