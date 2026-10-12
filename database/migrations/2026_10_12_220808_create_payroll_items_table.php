<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One employee's pay in a payroll run: earnings, mandatory contributions
 * (with the employer's share for remittance), tax, other deductions and net
 * pay. Office, position and rate are copied so a released payslip never
 * changes when the 201 file does.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->string('department_name');
            $table->string('position_title');
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('salary_step')->nullable();
            $table->decimal('monthly_rate', 12, 2)->nullable()->comment('Monthly salary (LGU rate) for salary-grade pay');
            $table->decimal('daily_rate', 10, 2)->nullable()->comment('Daily rate for Job Order / COS pay');
            $table->unsignedInteger('worked_minutes')->nullable()->comment('Job Order / COS: minutes worked in the period');

            $table->decimal('basic_pay', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('attendance_deductions', 12, 2)->default(0)->comment('Absences and tardiness without pay');
            $table->decimal('gross_pay', 12, 2);
            $table->json('earnings')->comment('Lines making up gross pay');

            $table->decimal('gsis', 12, 2)->default(0);
            $table->decimal('philhealth', 12, 2)->default(0);
            $table->decimal('pagibig', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);

            $table->decimal('gsis_employer', 12, 2)->default(0);
            $table->decimal('philhealth_employer', 12, 2)->default(0);
            $table->decimal('pagibig_employer', 12, 2)->default(0);

            $table->json('notes')->nullable()->comment('Explanations shown on the payslip and the register');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
