<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The leave card: an append-only record of every vacation and sick leave
 * credit earned, used, deducted or adjusted, with the balance after each line.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 20);
            $table->date('entry_date');
            $table->date('period')->nullable()->comment('First day of the month an accrual or tardiness deduction is for');
            $table->decimal('days', 8, 3)->comment('Positive adds credits, negative uses them');
            $table->decimal('balance_after', 8, 3);
            $table->decimal('days_without_pay', 8, 3)->default(0);
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('particulars');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'entry_date']);
            $table->index(['employee_id', 'period', 'entry_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
    }
};
