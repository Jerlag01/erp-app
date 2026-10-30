<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock item names in Dutch, French and English (one is enough; "name" keeps the first one filled in,
 * for searching and the places that show one name), and when a stock row was counted during the yearly count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->string('name_nl', 200)->nullable()->after('name');
            $table->string('name_fr', 200)->nullable()->after('name_nl');
            $table->string('name_en', 200)->nullable()->after('name_fr');
        });

        DB::table('stock_items')->update(['name_nl' => DB::raw('name')]);

        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->timestamp('counted_at')->nullable()->after('min_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropColumn('counted_at');
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropColumn(['name_nl', 'name_fr', 'name_en']);
        });
    }
};
