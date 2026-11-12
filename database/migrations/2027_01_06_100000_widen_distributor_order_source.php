<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "delivery_note" (Cebeo delivery notes) did not fit in the 10 characters of the source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributor_orders', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('distributor_orders', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->change();
        });
    }
};
