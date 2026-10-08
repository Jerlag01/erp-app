<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance: clients, outgoing invoices and credit notes (one table, with lines), the standard
 * lines every invoice starts with, the PO numbers an invoice bills (each PO once), incoming
 * invoices (expenses) with their validation, editable e-mail templates and the Peppol log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 30)->unique();
            $table->string('company_name');
            $table->boolean('is_private')->default(false);
            $table->json('emails')->nullable();
            $table->string('accounting_email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('addition', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vat_number', 30)->nullable()->index();
            $table->string('peppol_id', 60)->nullable();
            $table->string('default_vat_rate', 20)->default('21');
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->string('locale', 5)->default('nl');
            $table->boolean('send_reminders')->default(true);
            $table->text('internal_memo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('standard_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('vat_rate', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('finance_counters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('number', 30)->nullable()->unique();
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->unsignedInteger('number_sequence')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credited_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->boolean('allow_changes')->default(false);
            $table->date('issue_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('client_reference')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('structured_reference', 20)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->date('paid_on')->nullable();
            $table->unsignedTinyInteger('reminder_level')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->string('peppol_status', 20)->nullable();
            $table->string('peppol_message_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('vat_rate', 20)->default('21');
            $table->decimal('line_total', 14, 2)->default(0);
            $table->boolean('is_standard')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // A PO number is billed on one invoice only (credit notes refer to their invoice instead).
        Schema::create('invoice_po_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_po_number_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('sent_to');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->string('supplier_vat', 30)->nullable();
            $table->string('supplier_invoice_number', 60)->nullable();
            $table->boolean('is_credit_note')->default(false);
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('ubl_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_note')->nullable();
            $table->string('signature_path')->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_vat', 'supplier_invoice_number']);
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40);
            $table->string('locale', 5);
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });

        Schema::create('peppol_documents', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10);
            $table->string('document_type', 20);
            $table->nullableMorphs('documentable');
            $table->string('sender_id', 60)->nullable();
            $table->string('receiver_id', 60)->nullable();
            $table->string('status', 20);
            $table->string('message_id')->nullable();
            $table->string('xml_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('standard_invoice_lines')->insert([
            ['description' => 'Dossierkosten', 'quantity' => 1, 'unit' => null, 'unit_price' => 0, 'vat_rate' => null, 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'BTV-controle (keuring)', 'quantity' => 1, 'unit' => null, 'unit_price' => 0, 'vat_rate' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('peppol_documents');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('invoice_reminders');
        Schema::dropIfExists('invoice_po_numbers');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('finance_counters');
        Schema::dropIfExists('standard_invoice_lines');
        Schema::dropIfExists('clients');
    }
};
