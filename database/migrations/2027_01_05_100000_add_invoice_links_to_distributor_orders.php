<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rexel and Cebeo have no API that lists placed orders. Orders are therefore also built from the
 * distributor's Peppol invoices (matched to the distributor by VAT number), from the webshop basket and
 * from Cebeo delivery notes. The expense is the last invoice that delivered (part of) the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('vat_number', 30)->nullable()->after('name');
        });

        Schema::table('distributor_orders', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('order_reference_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('distributor_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn('vat_number');
        });
    }
};
