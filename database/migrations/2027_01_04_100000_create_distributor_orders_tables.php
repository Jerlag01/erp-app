<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The orders placed at the distributors (Purchasing > Orders, a tab per distributor with an API): what was
 * ordered with which references and how far the delivery is. Filled from the distributor's API (once it
 * offers orders) or by hand; linked to our own order reference ("466/1026/JL") when that reference is in
 * the order. Updating the stock from incoming orders comes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
            // The distributor's own order number.
            $table->string('external_number', 100)->nullable();
            // What we gave as reference when ordering (where our "466/1026/JL" is looked for).
            $table->string('customer_reference')->nullable();
            $table->foreignId('order_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->date('ordered_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->date('expected_delivery')->nullable();
            $table->date('delivered_at')->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('source', 10)->default('manual');
            $table->text('notes')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['distributor_id', 'external_number']);
            $table->index(['distributor_id', 'ordered_at']);
        });

        Schema::create('distributor_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number')->default(0);
            $table->string('distributor_reference', 100)->nullable();
            $table->string('manufacturer_reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('quantity_delivered', 12, 3)->default(0);
            $table->string('unit', 20)->nullable();
            $table->decimal('net_price', 12, 4)->nullable();
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expected_delivery')->nullable();
            $table->date('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::table('distributors', function (Blueprint $table) {
            $table->timestamp('orders_synced_at')->nullable()->after('api_customer_number');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn('orders_synced_at');
        });
        Schema::dropIfExists('distributor_order_lines');
        Schema::dropIfExists('distributor_orders');
    }
};
