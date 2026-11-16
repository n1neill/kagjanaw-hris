<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CSC service record: one row per period of government service. The
 * designation, status and station are kept as text as they were at the time,
 * so renaming a position or office later does not rewrite history, and
 * service rendered before the HRIS or in other agencies can be recorded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('service_from');
            $table->date('service_to')->nullable()->comment('Null means up to the present');
            $table->string('designation');
            $table->string('status', 100)->comment('Status of appointment, e.g. Permanent, Casual');
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->string('station')->nullable()->comment('Office or place of assignment');
            $table->string('branch', 100)->default('Local');
            $table->string('leave_without_pay', 100)->nullable();
            $table->date('separation_date')->nullable();
            $table->string('separation_cause')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'service_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_service_records');
    }
};
