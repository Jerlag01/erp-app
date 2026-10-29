<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The expiry of a fuel pass: a month and year (stored as the last day of that month).
     */
    public function up(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table) {
            $table->date('expires_on')->nullable()->after('pin_code');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_passes', function (Blueprint $table) {
            $table->dropColumn('expires_on');
        });
    }
};
