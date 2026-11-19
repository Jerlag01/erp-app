<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving invoices from more places (Doccle, a mailbox folder), importing earlier invoices from
 * UBL files, the export for the accountant, and reading the bank accounts (Ponto for ING, Belfius
 * and KBC, or CODA files) to see which invoices were paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            // The same PDF arriving twice (mail and Doccle) is only added once.
            $table->string('document_hash', 64)->nullable()->after('document_name')->index();
            // Where it came from: "doccle:<id>" or "mail:<message id>".
            $table->string('inbox_reference', 191)->nullable()->after('source')->index();
            // The supplier's payment reference (structured communication) from the UBL.
            $table->string('payment_reference', 60)->nullable()->after('due_date');
            $table->timestamp('accounting_exported_at')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // "erp" (made here) or "import" (an earlier invoice read from its UBL file).
            $table->string('source', 20)->default('erp')->after('type');
            $table->string('source_xml_path')->nullable();
            $table->string('original_pdf_path')->nullable();
            $table->timestamp('accounting_exported_at')->nullable();
        });

        Schema::create('integration_states', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_exports', function (Blueprint $table): void {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->json('parts');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('purchase_count')->default(0);
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('emailed_to', 1000)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('iban', 34)->unique();
            $table->string('bic', 11)->nullable();
            $table->string('bank', 20)->default('other');
            // "ponto" (read through Ponto) or "coda" (statements imported as CODA files).
            $table->string('provider', 20)->default('coda');
            $table->string('external_id', 100)->nullable()->index();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('balance', 14, 2)->nullable();
            $table->timestamp('balance_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('source', 20);
            $table->date('booking_date')->index();
            $table->date('value_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_iban', 34)->nullable();
            $table->text('remittance')->nullable();
            // The 12 digits of a Belgian structured communication, without +++ and slashes.
            $table->string('structured_reference', 12)->nullable()->index();
            $table->string('status', 20)->default('unmatched')->index();
            $table->nullableMorphs('matchable');
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('integration_states');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['source', 'source_xml_path', 'original_pdf_path', 'accounting_exported_at']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['document_hash', 'inbox_reference', 'payment_reference', 'accounting_exported_at']);
        });
    }
};
