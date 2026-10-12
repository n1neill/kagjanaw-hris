<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The BIR monthly withholding tax table on compensation. Tax on monthly
 * taxable compensation over a bracket's lower bound is the base tax plus the
 * rate on the excess.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->decimal('over', 12, 2)->comment('Monthly taxable compensation above which the bracket applies');
            $table->decimal('base_tax', 12, 2);
            $table->decimal('rate', 5, 2)->comment('Percent of the excess over the lower bound');
            $table->string('legal_basis')->nullable();
            $table->timestamps();

            $table->unique(['effective_from', 'over']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
