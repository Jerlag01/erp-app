<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The visual inspection of battery dossiers and moves: the parts "acid trays" and "only for installation
 * in a cabinet" can be marked not applicable (NVT); their questions are then not answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table): void {
            $table->json('inspection_na')->nullable()->after('inspection');
        });
    }

    public function down(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table): void {
            $table->dropColumn('inspection_na');
        });
    }
};
