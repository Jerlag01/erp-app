<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employee register: personal details, emergency contacts, certificates with PDF and
 * expiry date, and the yearly medical check-ups with the occupational doctor.
 * The login account (users) stays separate, so accounts can exist without an employee record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // The login account; kept (but deactivated) when the employee leaves.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('addition', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('private_email')->nullable();
            $table->string('private_phone', 50)->nullable();
            // Encrypted at rest (casts), hence text columns.
            $table->text('national_number')->nullable();
            $table->text('bank_account')->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('employment_date')->nullable();
            $table->string('mother_tongue', 10)->nullable();
            $table->string('employment_category', 30)->nullable();
            $table->string('contract_term', 30)->nullable();
            $table->string('education_level', 30)->nullable();
            $table->string('size_pants', 20)->nullable();
            $table->string('size_shirt', 20)->nullable();
            $table->string('size_sweater', 20)->nullable();
            $table->string('size_shoes', 20)->nullable();
            $table->string('emergency1_first_name', 100)->nullable();
            $table->string('emergency1_last_name', 100)->nullable();
            $table->string('emergency1_phone', 50)->nullable();
            $table->string('emergency1_relation', 100)->nullable();
            $table->string('emergency2_first_name', 100)->nullable();
            $table->string('emergency2_last_name', 100)->nullable();
            $table->string('emergency2_phone', 50)->nullable();
            $table->string('emergency2_relation', 100)->nullable();
            $table->text('notes')->nullable();
            $table->date('left_on')->nullable()->index();
            $table->string('leaving_reason')->nullable();
            $table->timestamp('welcome_sent_at')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('employee_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            // Driving licence categories (AM, A, B, BE, C, …) for the driving licence only.
            $table->json('categories')->nullable();
            $table->date('obtained_on')->nullable();
            // Empty when the certificate does not expire.
            $table->date('expires_on')->nullable()->index();
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'type']);
        });

        Schema::create('employee_medical_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->string('result', 30);
            $table->date('next_due_on')->nullable();
            $table->string('doctor')->nullable();
            $table->text('remarks')->nullable();
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_medical_checks');
        Schema::dropIfExists('employee_certificates');
        Schema::dropIfExists('employees');
    }
};
