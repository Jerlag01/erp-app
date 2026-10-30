<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fuel pass card number has 18 digits, not 19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table): void {
            $table->char('card_number', 18)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table): void {
            $table->char('card_number', 19)->change();
        });
    }
};
