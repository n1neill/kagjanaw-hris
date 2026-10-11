<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Government ID numbers are stored encrypted (TEXT, via Eloquent casts),
     * so they cannot carry database unique constraints.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('name_extension', 10)->nullable();
            $table->string('sex', 10);
            $table->string('civil_status', 20);
            $table->date('birth_date');
            $table->string('birth_place')->nullable();

            $table->string('mobile_number', 20)->nullable();
            $table->string('residential_address', 500)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 20)->nullable();

            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('salary_step')->nullable();
            $table->date('date_hired');
            $table->string('employment_status', 20)->default('active')->index();
            $table->date('separation_date')->nullable();

            $table->text('gsis_number')->nullable();
            $table->text('philhealth_number')->nullable();
            $table->text('pagibig_number')->nullable();
            $table->text('tin')->nullable();
            $table->string('biometric_user_id', 20)->nullable()->unique();

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
