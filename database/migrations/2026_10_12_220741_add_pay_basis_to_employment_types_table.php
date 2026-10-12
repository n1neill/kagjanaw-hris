<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How employees of a type are paid: by salary grade and step, or by a daily
 * rate (Job Order and Contract of Service), which also decides their payroll.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employment_types', function (Blueprint $table) {
            $table->string('pay_basis', 20)->default('salary-grade')->after('earns_leave_credits');
        });

        DB::table('employment_types')
            ->whereIn('code', ['job-order', 'contract-of-service'])
            ->update(['pay_basis' => 'daily-rate']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_types', function (Blueprint $table) {
            $table->dropColumn('pay_basis');
        });
    }
};
