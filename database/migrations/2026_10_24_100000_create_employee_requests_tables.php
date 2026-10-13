<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employees keep their own personal details up to date and add their own certificates, but nothing
 * is changed before an administrator approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // Encrypted JSON: field => new value, and field => value at the time of the request.
            $table->text('new_values');
            $table->text('old_values');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->json('categories')->nullable();
            $table->date('obtained_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->string('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            // The certificate it created or renewed.
            $table->foreignId('employee_certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certificate_requests');
        Schema::dropIfExists('employee_change_requests');
    }
};
