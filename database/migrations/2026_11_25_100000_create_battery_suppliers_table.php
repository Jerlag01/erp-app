<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Battery suppliers the BAE orders are mailed to (with a button on the order, never automatically),
 * and when and to whom each order was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150);
            // Extra addresses at the supplier that get the order too (cc).
            $table->json('cc_emails')->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            // Language of the order mail.
            $table->string('locale', 5)->default('en');
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('bae_orders', function (Blueprint $table) {
            $table->foreignId('battery_supplier_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('exported_at');
            $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            // "orders@bae.de; cc: inkoop@…" as it went out.
            $table->text('sent_to')->nullable()->after('sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('bae_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('battery_supplier_id');
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn(['sent_at', 'sent_to']);
        });

        Schema::dropIfExists('battery_suppliers');
    }
};
