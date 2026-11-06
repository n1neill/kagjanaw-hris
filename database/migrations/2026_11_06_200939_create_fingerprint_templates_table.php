<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrolled fingerprints for the DigitalPersona kiosk. Only the matcher's
 * template is kept (encrypted), never the fingerprint image (RA 10173).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fingerprint_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('finger', 20);
            $table->text('template');
            $table->unsignedTinyInteger('quality')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'finger']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fingerprint_templates');
    }
};
