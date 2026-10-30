<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A delivery line made from a sent BAE order takes the requested delivery date of the order, which the
 * supplier still has to confirm ("to be confirmed" until it is changed or ticked as confirmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_deliveries', function (Blueprint $table) {
            $table->boolean('delivery_date_confirmed')->default(true)->after('planned_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('battery_deliveries', function (Blueprint $table) {
            $table->dropColumn('delivery_date_confirmed');
        });
    }
};
