<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes submitted by a user who holds an HR permission as a direct grant
 * rather than through their role. Nothing is saved to the actual records
 * until an HR Officer approves the change. The payload is encrypted because
 * it may hold government ID numbers (RA 10173).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('action', 60);
            $table->string('description', 255);
            $table->nullableMorphs('subject');
            $table->longText('payload');
            $table->json('files')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_remarks', 1000)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
