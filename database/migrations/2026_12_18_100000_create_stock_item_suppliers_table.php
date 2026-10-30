<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An article can be bought from several suppliers (distributors), each with its own article number and
 * price: fetched through the supplier's connection, or entered by hand when it has none. The item's own
 * distributor, reference and market price follow its preferred supplier. The price history keeps the supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_item_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 100)->nullable();
            $table->decimal('price', 12, 4)->nullable();
            $table->string('price_source', 30)->nullable();
            $table->timestamp('price_updated_at')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['stock_item_id', 'distributor_id']);
        });

        Schema::table('stock_item_prices', function (Blueprint $table): void {
            $table->foreignId('distributor_id')->nullable()->after('stock_item_id')->constrained()->nullOnDelete();
        });

        // The distributor each item has now becomes its first, preferred supplier.
        DB::table('stock_items')->whereNotNull('distributor_id')->orderBy('id')->chunkById(500, function ($items): void {
            DB::table('stock_item_suppliers')->insert($items->map(fn (object $item): array => [
                'stock_item_id' => $item->id,
                'distributor_id' => $item->distributor_id,
                'reference' => $item->distributor_reference,
                'price' => $item->market_price,
                'price_source' => $item->price_source,
                'price_updated_at' => $item->price_updated_at,
                'is_preferred' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        DB::table('stock_item_prices')
            ->join('stock_items', 'stock_items.id', '=', 'stock_item_prices.stock_item_id')
            ->whereNotNull('stock_items.distributor_id')
            ->update(['stock_item_prices.distributor_id' => DB::raw('stock_items.distributor_id')]);
    }

    public function down(): void
    {
        Schema::table('stock_item_prices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('distributor_id');
        });

        Schema::dropIfExists('stock_item_suppliers');
    }
};
