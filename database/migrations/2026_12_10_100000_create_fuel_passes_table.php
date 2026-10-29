<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuel passes of the fleet: the card number (19 digits) and its secret PIN (4 digits, encrypted),
     * assigned to one vehicle.
     */
    public function up(): void
    {
        Schema::create('fuel_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->char('card_number', 19)->unique();
            $table->text('pin_code');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_passes');
    }
};
