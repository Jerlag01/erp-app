<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The total battery voltage of the measurement report is measured and filled in, no longer added up from
 * the cells: total_voltage ("Eindspanning", for Enersys disconnected) and floating_total_voltage (Enersys
 * floating). Existing measurements keep the totals they had, the sum of their cells.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossier_batteries', function (Blueprint $table) {
            $table->decimal('total_voltage', 8, 2)->nullable()->after('cell_densities');
            $table->decimal('floating_total_voltage', 8, 2)->nullable()->after('floating_voltages');
        });

        $sum = function (?string $json): ?float {
            $values = array_filter((array) json_decode((string) $json, true), 'is_numeric');

            return $values === [] ? null : round(array_sum(array_map('floatval', $values)), 2);
        };

        DB::table('battery_dossier_batteries')
            ->where(fn ($query) => $query->whereNotNull('cell_voltages')->orWhereNotNull('floating_voltages'))
            ->orderBy('id')
            ->each(function (object $row) use ($sum): void {
                DB::table('battery_dossier_batteries')->where('id', $row->id)->update([
                    'total_voltage' => $sum($row->cell_voltages),
                    'floating_total_voltage' => $sum($row->floating_voltages),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('battery_dossier_batteries', function (Blueprint $table) {
            $table->dropColumn(['total_voltage', 'floating_total_voltage']);
        });
    }
};
