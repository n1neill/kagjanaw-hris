<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw punches as received from devices or CSV imports. The unique index on
 * (biometric_user_id, logged_at) makes synchronization idempotent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('biometric_user_id', 20);
            $table->dateTime('logged_at');
            $table->string('verify_type', 20);
            $table->string('source', 20);
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['biometric_user_id', 'logged_at']);
            $table->index(['employee_id', 'logged_at']);
            $table->index(['status', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
