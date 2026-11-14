<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DC cable calculations use their own formula (the Proximus 48 V calculation note):
 * S = I × L × 2 × ρ / ΔU, with the rating of the breaker or fuse, the resistivity, the allowed
 * voltage drop in volt and the section that is provided ("voorzien voor").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->string('protection_type', 10)->nullable()->after('protective_device');
            $table->string('protection_size', 10)->nullable()->after('protection_type');
            $table->decimal('resistivity', 8, 5)->nullable()->after('protection_size');
            $table->decimal('max_drop_volts', 6, 2)->nullable()->after('max_voltage_drop');
            $table->decimal('chosen_section', 6, 1)->nullable()->after('max_drop_volts');
        });

        // Existing DC calculations: the allowed drop in volt from the percentage, copper's resistivity,
        // the breaker rating the old calculation used (the next standard rating above the current).
        $ratings = [6, 10, 13, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125, 160, 200, 250, 315, 400, 500, 630, 800, 1000, 1250];

        DB::table('cable_calculations')->where('system', 'dc')->orderBy('id')->each(function (object $row) use ($ratings): void {
            $current = (float) $row->load_current;
            $device = $row->protective_device ?: (collect($ratings)->first(fn (int $rating): bool => $rating >= $current) ?? ($current ?: null));

            DB::table('cable_calculations')->where('id', $row->id)->update([
                'protective_device' => $device,
                'protection_type' => 'breaker',
                'resistivity' => 0.0184,
                'max_drop_volts' => round((float) $row->max_voltage_drop * (float) $row->voltage / 100, 2) ?: 1,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->dropColumn(['protection_type', 'protection_size', 'resistivity', 'max_drop_volts', 'chosen_section']);
        });
    }
};
