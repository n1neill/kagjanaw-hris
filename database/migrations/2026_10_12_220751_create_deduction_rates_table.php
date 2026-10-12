<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSIS, PhilHealth and Pag-IBIG contribution rates with their effectivity.
 * The contribution is the rate times the monthly basic salary, kept within
 * the salary floor and ceiling.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deduction_rates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->date('effective_from');
            $table->decimal('employee_rate', 5, 2)->comment('Percent of the monthly basic salary');
            $table->decimal('employer_rate', 5, 2)->comment('Percent of the monthly basic salary');
            $table->decimal('salary_floor', 12, 2)->nullable();
            $table->decimal('salary_ceiling', 12, 2)->nullable();
            $table->string('legal_basis')->nullable();
            $table->timestamps();

            $table->unique(['type', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_rates');
    }
};
