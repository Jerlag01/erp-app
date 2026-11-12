<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock (sub)categories in three languages: "name" is the Dutch name, French and English are filled in
 * automatically (DeepL) when empty, and can be changed by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_categories', function (Blueprint $table) {
            $table->string('name_fr')->nullable()->after('name');
            $table->string('name_en')->nullable()->after('name_fr');
        });
    }

    public function down(): void
    {
        Schema::table('stock_categories', function (Blueprint $table) {
            $table->dropColumn(['name_fr', 'name_en']);
        });
    }
};
