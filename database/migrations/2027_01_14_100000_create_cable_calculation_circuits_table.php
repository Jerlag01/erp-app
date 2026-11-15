<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cable calculation holds several circuits, AC or DC each, from a start point to an end point
 * ("Krachtbord → Batterij 1"). Every existing calculation becomes one circuit.
 */
return new class extends Migration
{
    /** The input and result columns that move from the calculation to its circuits. */
    protected const COLUMNS = [
        'system', 'voltage', 'load_type', 'load_current', 'load_power', 'power_factor', 'length', 'material', 'insulation',
        'installation_method', 'ambient_temperature', 'grouped_circuits', 'parallel_conductors', 'max_voltage_drop',
        'max_drop_volts', 'chosen_section', 'protective_device', 'protection_type', 'protection_size', 'resistivity',
        'short_circuit_ka', 'short_circuit_time', 'result',
    ];

    public function up(): void
    {
        Schema::create('cable_calculation_circuits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cable_calculation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('from_point')->nullable();
            $table->string('to_point')->nullable();
            $table->string('system', 10);
            $table->decimal('voltage', 8, 1)->nullable();
            $table->string('load_type', 10)->default('current');
            $table->decimal('load_current', 10, 2)->nullable();
            $table->decimal('load_power', 10, 2)->nullable();
            $table->decimal('power_factor', 4, 2)->nullable();
            $table->decimal('length', 10, 1)->nullable();
            $table->string('material', 5)->nullable();
            $table->string('insulation', 5)->nullable();
            $table->string('installation_method', 5)->nullable();
            $table->smallInteger('ambient_temperature')->nullable();
            $table->unsignedSmallInteger('grouped_circuits')->default(1);
            $table->unsignedSmallInteger('parallel_conductors')->default(1);
            $table->decimal('max_voltage_drop', 5, 2)->nullable();
            $table->decimal('max_drop_volts', 6, 2)->nullable();
            $table->decimal('chosen_section', 6, 1)->nullable();
            $table->decimal('protective_device', 8, 1)->nullable();
            $table->string('protection_type', 10)->nullable();
            $table->string('protection_size', 10)->nullable();
            $table->decimal('resistivity', 8, 5)->nullable();
            $table->decimal('short_circuit_ka', 8, 2)->nullable();
            $table->decimal('short_circuit_time', 6, 3)->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });

        DB::table('cable_calculations')->orderBy('id')->each(function (object $row): void {
            DB::table('cable_calculation_circuits')->insert([
                'cable_calculation_id' => $row->id,
                'sort_order' => 1,
                'from_point' => null,
                'to_point' => $row->name,
                ...collect(self::COLUMNS)->mapWithKeys(fn (string $column): array => [$column => $row->{$column}])->all(),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        });

        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }

    public function down(): void
    {
        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->string('system', 10)->default('ac3');
            $table->decimal('voltage', 8, 1)->default(400);
            $table->string('load_type', 10)->default('current');
            $table->decimal('load_current', 10, 2)->nullable();
            $table->decimal('load_power', 10, 2)->nullable();
            $table->decimal('power_factor', 4, 2)->nullable();
            $table->decimal('length', 10, 1)->default(0);
            $table->string('material', 5)->default('cu');
            $table->string('insulation', 5)->default('xlpe');
            $table->string('installation_method', 5)->default('C');
            $table->smallInteger('ambient_temperature')->default(30);
            $table->unsignedSmallInteger('grouped_circuits')->default(1);
            $table->unsignedSmallInteger('parallel_conductors')->default(1);
            $table->decimal('max_voltage_drop', 5, 2)->default(3);
            $table->decimal('max_drop_volts', 6, 2)->nullable();
            $table->decimal('chosen_section', 6, 1)->nullable();
            $table->decimal('protective_device', 8, 1)->nullable();
            $table->string('protection_type', 10)->nullable();
            $table->string('protection_size', 10)->nullable();
            $table->decimal('resistivity', 8, 5)->nullable();
            $table->decimal('short_circuit_ka', 8, 2)->nullable();
            $table->decimal('short_circuit_time', 6, 3)->nullable();
            $table->json('result')->nullable();
        });

        // Back to one circuit per calculation: the first one.
        DB::table('cable_calculation_circuits')->orderBy('sort_order')->orderBy('id')->get()->unique('cable_calculation_id')->each(function (object $circuit): void {
            DB::table('cable_calculations')->where('id', $circuit->cable_calculation_id)->update(
                collect(self::COLUMNS)->mapWithKeys(fn (string $column): array => [$column => $circuit->{$column}])->filter(fn (mixed $value): bool => $value !== null)->all()
            );
        });

        Schema::dropIfExists('cable_calculation_circuits');
    }
};
