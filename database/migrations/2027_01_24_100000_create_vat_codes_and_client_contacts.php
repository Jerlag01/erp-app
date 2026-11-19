<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT codes become editable (finance master data) instead of a fixed list. The code is what the
 * invoice lines, standard lines and clients store, so the existing values (21, 6, 0, reverse_charge)
 * become the first codes and nothing has to be converted. A code can be a reverse charge
 * (autoliquidatie / medecontractant): no VAT on the invoice and its legal mention on the PDF.
 *
 * Clients get communication points for invoicing: contact persons with their own e-mail address who
 * receive the invoices and/or the payment reminders. An invoice can be addressed to one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('name_fr', 100)->nullable();
            $table->string('name_en', 100)->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            // UNCL5305 for Peppol: S standard, Z zero rated, E exempt, AE reverse charge, K intra-EU, G export, O outside scope.
            $table->string('category', 2)->default('S');
            $table->boolean('is_reverse_charge')->default(false);
            $table->string('exemption_reason_code', 30)->nullable();
            $table->text('mention')->nullable();
            $table->text('mention_fr')->nullable();
            $table->text('mention_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        $codes = [
            ['code' => '21', 'name' => '21%', 'name_fr' => '21%', 'name_en' => '21%', 'percentage' => 21, 'category' => 'S', 'sort' => 1],
            ['code' => '12', 'name' => '12%', 'name_fr' => '12%', 'name_en' => '12%', 'percentage' => 12, 'category' => 'S', 'sort' => 2],
            ['code' => '6', 'name' => '6%', 'name_fr' => '6%', 'name_en' => '6%', 'percentage' => 6, 'category' => 'S', 'sort' => 3],
            ['code' => '0', 'name' => '0%', 'name_fr' => '0%', 'name_en' => '0%', 'percentage' => 0, 'category' => 'Z', 'sort' => 4],
            [
                'code' => 'reverse_charge', 'name' => 'Btw verlegd', 'name_fr' => 'Autoliquidation', 'name_en' => 'Reverse charge',
                'percentage' => 0, 'category' => 'AE', 'is_reverse_charge' => true, 'exemption_reason_code' => 'VATEX-EU-AE', 'sort' => 5,
                'mention' => 'Btw verlegd – art. 51 §2 W.BTW. De medecontractant is de btw verschuldigd.',
                'mention_fr' => 'Autoliquidation – art. 51 §2 du Code TVA. La TVA est due par le cocontractant.',
                'mention_en' => 'VAT reverse charge – art. 51 §2 Belgian VAT Code. The customer accounts for the VAT.',
            ],
            [
                'code' => 'intra_eu', 'name' => 'Intracommunautair', 'name_fr' => 'Intracommunautaire', 'name_en' => 'Intra-community',
                'percentage' => 0, 'category' => 'K', 'is_reverse_charge' => true, 'exemption_reason_code' => 'VATEX-EU-IC', 'sort' => 6,
                'mention' => 'Intracommunautaire dienst/levering – btw verlegd (art. 21 §2 en art. 39bis W.BTW / art. 196 Richtlijn 2006/112/EG).',
                'mention_fr' => 'Prestation/livraison intracommunautaire – autoliquidation (art. 21 §2 et art. 39bis du Code TVA / art. 196 Directive 2006/112/CE).',
                'mention_en' => 'Intra-community supply – reverse charge (art. 21 §2 and art. 39bis Belgian VAT Code / art. 196 Directive 2006/112/EC).',
                'is_active' => false,
            ],
            [
                'code' => 'export', 'name' => 'Export buiten EU', 'name_fr' => 'Exportation hors UE', 'name_en' => 'Export outside EU',
                'percentage' => 0, 'category' => 'G', 'exemption_reason_code' => 'VATEX-EU-G', 'sort' => 7,
                'mention' => 'Vrijgesteld van btw – uitvoer (art. 39 W.BTW).',
                'mention_fr' => 'Exonération de TVA – exportation (art. 39 du Code TVA).',
                'mention_en' => 'Exempt from VAT – export (art. 39 Belgian VAT Code).',
                'is_active' => false,
            ],
        ];

        foreach ($codes as $code) {
            DB::table('vat_codes')->insert([
                'is_reverse_charge' => false, 'exemption_reason_code' => null, 'mention' => null, 'mention_fr' => null, 'mention_en' => null, 'is_active' => true,
                ...$code,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::create('client_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('role', 100)->nullable();
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->boolean('receives_invoices')->default(true);
            $table->boolean('receives_reminders')->default(true);
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('client_contact_id')->nullable()->after('client_id')->constrained('client_contacts')->nullOnDelete();
            $table->string('sent_to', 1000)->nullable()->change();
        });

        Schema::table('invoice_reminders', function (Blueprint $table): void {
            $table->string('sent_to', 1000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_contact_id');
        });
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('vat_codes');
    }
};
