<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuel pass card numbers have a variable length (depending on the supplier): digits only, up to 50.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table): void {
            $table->string('card_number', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table): void {
            $table->char('card_number', 18)->change();
        });
    }
};
