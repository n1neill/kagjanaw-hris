<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether employees of a type earn vacation and sick leave credits. Elective
 * officials and Job Order / Contract of Service workers do not.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employment_types', function (Blueprint $table) {
            $table->boolean('earns_leave_credits')->default(true)->after('description');
        });

        DB::table('employment_types')
            ->whereIn('code', ['elective', 'job-order', 'contract-of-service'])
            ->update(['earns_leave_credits' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_types', function (Blueprint $table) {
            $table->dropColumn('earns_leave_credits');
        });
    }
};
