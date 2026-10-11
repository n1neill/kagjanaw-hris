<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users now sign in with a username (the employee ID for employees), so the
 * email address becomes optional. Accounts created or reset by someone else
 * are flagged so the user must choose their own password on next sign-in.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        DB::table('users')->whereNull('username')->update(['username' => DB::raw('email')]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->unique()->change();
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Accounts without an email address cannot be restored to the old schema,
     * which required one; the rollback fails rather than discarding them.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'must_change_password']);
        });
    }
};
