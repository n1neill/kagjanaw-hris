<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national monthly Salary Schedule (SSL), one row per grade and step for
 * each tranche. The LGU rate is this amount times the income-class percentage.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->unsignedTinyInteger('grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('amount', 12, 2);
            $table->string('legal_basis')->nullable();
            $table->timestamps();

            $table->unique(['effective_from', 'grade', 'step']);
            $table->index(['grade', 'step', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
