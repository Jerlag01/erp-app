<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AC circuits: the earthing system, the frequency, the maximum cable section (beyond it cables are put in
 * parallel), whether the protective conductor is in the cable, and the load in kVA. The nets become
 * 3F+N, 3F, 2F and 1F+N: the old single-phase code becomes 1F+N.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cable_calculation_circuits', function (Blueprint $table) {
            $table->string('earthing', 10)->default('undefined')->after('voltage');
            $table->decimal('frequency', 5, 1)->default(50)->after('earthing');
            $table->decimal('load_kva', 10, 2)->nullable()->after('load_power');
            $table->decimal('max_section', 6, 1)->nullable()->after('max_voltage_drop');
            $table->boolean('earth_in_cable')->default(true)->after('max_section');
        });

        DB::table('cable_calculation_circuits')->where('system', 'ac1')->update(['system' => 'ac1n']);
    }

    public function down(): void
    {
        DB::table('cable_calculation_circuits')->where('system', 'ac1n')->update(['system' => 'ac1']);
        DB::table('cable_calculation_circuits')->whereIn('system', ['ac3n', 'ac2'])->update(['system' => 'ac3']);

        Schema::table('cable_calculation_circuits', function (Blueprint $table) {
            $table->dropColumn(['earthing', 'frequency', 'load_kva', 'max_section', 'earth_in_cable']);
        });
    }
};
