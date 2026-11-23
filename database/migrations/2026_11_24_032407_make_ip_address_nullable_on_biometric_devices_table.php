<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A kiosk may be registered without an address, so its token is accepted from
 * any address (e.g. an HRIS on the internet and an office whose IP changes).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Kiosks without an address get 0.0.0.0, which no
     * request comes from, so they are locked out until an address is entered.
     */
    public function down(): void
    {
        DB::table('biometric_devices')->whereNull('ip_address')->update(['ip_address' => '0.0.0.0']);

        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable(false)->change();
        });
    }
};
