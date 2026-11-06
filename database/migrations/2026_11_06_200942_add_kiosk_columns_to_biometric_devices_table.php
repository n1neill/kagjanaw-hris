<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosk devices (DigitalPersona) sign their requests with a token, stored
 * here only as a SHA-256 hash, and check in regularly (last_seen_at).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique()->after('is_active');
            $table->timestamp('last_seen_at')->nullable()->after('last_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'last_seen_at']);
        });
    }
};
