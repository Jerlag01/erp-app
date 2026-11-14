<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offer lines can be warehouse stock items: the item's price as cost price, times a profit factor per
 * line, gives the supply price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_lines', function (Blueprint $table) {
            $table->foreignId('stock_item_id')->nullable()->after('offer_category_id')->constrained()->nullOnDelete();
            $table->decimal('cost_price', 12, 2)->nullable()->after('unit');
            $table->decimal('factor', 6, 3)->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('offer_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_item_id');
            $table->dropColumn(['cost_price', 'factor']);
        });
    }
};
