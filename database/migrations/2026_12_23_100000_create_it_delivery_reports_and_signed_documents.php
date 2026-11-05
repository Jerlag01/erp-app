<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IT asset delivery reports (the "ontvangstbewijs" an employee signs when an asset is handed to them) and
 * the employee's signed documents: the signed PDFs, kept per employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_delivery_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->nullable()->constrained('it_assets')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('handed_over_on');
            $table->json('details');
            $table->string('company_signer_name', 150)->nullable();
            $table->string('company_signature_path')->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'signed_at']);
        });

        Schema::create('signed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->string('title');
            $table->string('path');
            $table->nullableMorphs('source');
            $table->timestamp('signed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_documents');
        Schema::dropIfExists('it_delivery_reports');
    }
};
