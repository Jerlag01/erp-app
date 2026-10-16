<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language of the documents of a battery dossier: empty follows the region of the COW site
 * (Flanders Dutch, Wallonia French, Brussels as set), or set by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->string('document_locale', 5)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->dropColumn('document_locale');
        });
    }
};
