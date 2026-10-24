<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documents of a battery delivery: the order sent to the supplier, the supplier's confirmation
 * and the delivery note when the batteries arrive. Internal: never shown in the client portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_deliveries', function (Blueprint $table) {
            foreach (['order_document', 'confirmation_document', 'delivery_note_document'] as $column) {
                $table->string($column, 500)->nullable();
                $table->string($column.'_name', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('battery_deliveries', function (Blueprint $table) {
            $table->dropColumn(['order_document', 'order_document_name', 'confirmation_document', 'confirmation_document_name', 'delivery_note_document', 'delivery_note_document_name']);
        });
    }
};
