<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enersys battery dossiers (605 projects, the Excel "12V enersys") live next to the BAE dossiers (604):
 * - kind: "bae" or "enersys";
 * - the Enersys type (12V62FS…), the capacity as written on the order ("155/100") and the acid leak trays;
 * - the amounts of the order ("Bestelling PI");
 * - per battery the float voltage and the block voltages while floating (the meetrapport).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->string('kind', 20)->default('bae')->after('id')->index();
            $table->string('battery_type', 30)->nullable()->after('brand');
            $table->string('new_capacity_label', 30)->nullable()->after('new_capacity_ah');
            $table->unsignedSmallInteger('leak_trays')->nullable();
            $table->json('order_amounts')->nullable();
        });

        Schema::table('battery_dossier_batteries', function (Blueprint $table) {
            $table->decimal('float_voltage', 7, 2)->nullable();
            $table->json('floating_voltages')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('battery_dossier_batteries', function (Blueprint $table) {
            $table->dropColumn(['float_voltage', 'floating_voltages']);
        });

        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->dropColumn(['kind', 'battery_type', 'new_capacity_label', 'leak_trays', 'order_amounts']);
        });
    }
};
