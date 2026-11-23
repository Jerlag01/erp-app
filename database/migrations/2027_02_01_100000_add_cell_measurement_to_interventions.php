<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions that replace bad battery cells: a category marked "measures_cells" asks, when closing,
 * for the measurement of the replaced cells (how many is filled in) and of the full battery (24 cells).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_categories', function (Blueprint $table): void {
            $table->boolean('measures_cells')->default(false)->after('frees_material');
        });

        Schema::table('intervention_reports', function (Blueprint $table): void {
            $table->json('cell_measurement')->nullable()->after('modified_boards');
        });
    }

    public function down(): void
    {
        Schema::table('intervention_reports', function (Blueprint $table): void {
            $table->dropColumn('cell_measurement');
        });

        Schema::table('intervention_categories', function (Blueprint $table): void {
            $table->dropColumn('measures_cells');
        });
    }
};
