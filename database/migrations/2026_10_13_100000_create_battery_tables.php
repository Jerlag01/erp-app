<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Battery dossiers (replacing stationary batteries on 604 projects), recreated from the Excel
 * "Online Dossier BAE NL": input, delivery note, measurement report, visual inspection, floor plan,
 * single-line diagram, shipping note and invoice request. Master data: battery models with their
 * rack per mounting type, and Frötek cables. Fixed texts and prices live in Settings > Batteries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // OPzV, OPzS, …
            $table->string('technology', 20);
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('battery_racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battery_model_id')->constrained()->cascadeOnDelete();
            // lijn, trap, u_vorm or kast.
            $table->string('mounting', 10);
            $table->string('code', 30)->nullable();
            // Price per cell.
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['battery_model_id', 'mounting']);
        });

        Schema::create('battery_cables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('battery_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('po_number', 20)->nullable();
            $table->string('contact_person')->nullable();
            $table->date('order_date')->nullable();
            $table->string('truck_plate', 50)->nullable();
            $table->string('waste_type')->nullable();
            $table->date('execution_date')->nullable();
            // Old batteries (removed).
            $table->unsignedInteger('old_capacity_ah')->nullable();
            $table->string('old_technology', 20)->nullable();
            $table->unsignedTinyInteger('old_count')->default(0);
            $table->boolean('removal')->default(true);
            $table->string('old_room', 50)->nullable();
            // New batteries (placed).
            $table->unsignedInteger('new_capacity_ah')->nullable();
            $table->string('new_technology', 20)->nullable();
            $table->unsignedTinyInteger('new_count')->default(1);
            // ja (we place), pi (delivered by PI) or nee (already on site).
            $table->string('delivery_mode', 10)->default('ja');
            $table->string('supplier_location')->nullable();
            $table->string('new_room', 50)->nullable();
            $table->decimal('volts_per_cell', 5, 2)->default(2);
            $table->string('first_id_number', 30)->nullable();
            $table->string('brand', 50)->nullable();
            $table->foreignId('battery_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mounting', 10)->nullable();
            $table->unsignedTinyInteger('cells_per_battery')->default(24);
            $table->string('production_date', 30)->nullable();
            // Delivery note quantities.
            $table->decimal('vertical_install_m', 8, 2)->default(0);
            $table->decimal('vertical_removal_m', 8, 2)->default(0);
            $table->unsignedInteger('ppe_sets')->default(0);
            $table->unsignedInteger('maintenance_sets')->default(0);
            $table->unsignedInteger('load_plates')->default(0);
            $table->decimal('man_hours', 8, 2)->default(0);
            $table->decimal('powerflex_length_m', 8, 2)->default(0);
            $table->string('powerflex_section', 20)->nullable();
            $table->foreignId('battery_cable_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('frotek_count')->default(0);
            $table->unsignedInteger('lug_count')->default(0);
            $table->string('lug_section', 20)->nullable();
            $table->unsignedInteger('fuse_count')->default(0);
            $table->string('fuse_amperage', 20)->nullable();
            $table->string('fuse_type', 30)->nullable();
            $table->decimal('tray_length_m', 8, 2)->default(0);
            $table->string('tray_type', 50)->nullable();
            $table->unsignedInteger('picto_plates')->default(0);
            $table->unsignedInteger('doc_holders')->default(0);
            // Prices (filled from the settings when the dossier is made, editable per dossier).
            $table->decimal('battery_total_price', 12, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('fuse_price', 10, 2)->default(0);
            $table->decimal('powerflex_price', 10, 2)->default(0);
            $table->decimal('lug_price', 10, 2)->default(0);
            $table->decimal('tray_price', 10, 2)->default(0);
            $table->decimal('doc_holder_price', 10, 2)->default(0);
            $table->decimal('picto_price', 10, 2)->default(0);
            $table->string('invoice_kind', 20)->default('final');
            $table->decimal('invoice_percentage', 5, 2)->default(100);
            // Measurement report.
            $table->date('measured_on')->nullable();
            $table->decimal('average_temperature', 5, 1)->nullable();
            // Visual inspection: {"cells_identical": "yes", …}.
            $table->json('inspection')->nullable();
            $table->text('inspection_remarks')->nullable();
            // Floor plan: vector drawing (json) and its PNG for the PDF.
            $table->json('floor_plan')->nullable();
            $table->string('floor_plan_png')->nullable();
            // Shipping note.
            $table->unsignedInteger('acid_boxes')->default(0);
            $table->unsignedInteger('empty_boxes_returned')->default(0);
            $table->unsignedInteger('packages')->default(0);
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per new battery (string): IDs, single-line diagram values and measured cell voltages.
        Schema::create('battery_dossier_batteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battery_dossier_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('id_number', 30)->nullable();
            $table->string('cable_type', 30)->nullable();
            $table->decimal('cable_length_m', 8, 2)->nullable();
            $table->unsignedTinyInteger('cables_positive')->nullable();
            $table->unsignedTinyInteger('cables_negative')->nullable();
            $table->string('cable_section', 20)->nullable();
            $table->string('fuse_amperage', 20)->nullable();
            $table->string('fuse_type', 30)->nullable();
            $table->json('cell_voltages')->nullable();
            $table->json('cell_densities')->nullable();
            $table->timestamps();

            $table->unique(['battery_dossier_id', 'position']);
        });

        $this->seed();
    }

    /**
     * The battery list of the Excel (sheet "Batterijen"): model, weight, l × b × h and the rack per mounting.
     */
    protected function seed(): void
    {
        $now = now();
        $models = [
            // name, technology, kg, l, b, h, [lijn, u_vorm, trap, kast] as [code, price]
            ['4 OPzV 200', 'OPzV', 19.5, 105, 208, 420, [['PGL1-29', 11.8], ['PGL2-15', 11.8], ['PGL3-11', 11.8], ['Kast', 65.3]]],
            ['6 OPzV 420', 'OPzV', 36.5, 147, 208, 535, [['PGL1-39H', 16.19], ['PGL2-20H', 16.19], ['SGL2-20H', 16.19], ['Kast', 65.3]]],
            ['7 OPzV 490', 'OPzV', 42, 168, 208, 535, [['PGL1-44H', 16.7], ['PGL2-23H', 16.7], ['SGL2-23H', 16.7], ['Kast', 65.3]]],
            ['6 OPzV 600', 'OPzV', 50, 147, 208, 710, [['PGL1-39H', 16.85], ['PGL2-20H', 16.85], ['SGL2-20H', 16.85], ['Kast', 96.4]]],
            ['8 OPzV 800', 'OPzV', 68, 215, 193, 710, [['PGL1-54H', 20.4], ['PGL2-27H', 20.4], ['SGL2-27H', 20.4], ['Kast', 107.81]]],
            ['10 OPzV 1000', 'OPzV', 82, 215, 235, 710, [['PGS1-54HH', 23.63], ['PGS2-27HH', 23.63], ['SGS2-27HH', 23.63], ['Kast', 107.81]]],
            ['12 OPzV 1200', 'OPzV', 97, 215, 277, 710, [['PGS1-54HH', 25.57], ['PGS2-27HH', 25.57], ['SGS2-27HH', 25.57], ['Kast', 173.14]]],
            ['12 OPzV 1500', 'OPzV', 120, 215, 277, 855, [['PGS1-54HH', 39.72], ['PGS2-27HH', 39.72], ['SGS2-27HH', 39.72], ['Kast', 173.14]]],
            ['16 OPzV 2000', 'OPzV', 160, 215, 400, 815, [['PGT1-51HH', 42.56], ['2xPGT1-27HH', 42.56], ['SGT2-27HH', 42.56], ['Kast', 221.94]]],
            ['20 OPzV 2500', 'OPzV', 200, 215, 490, 815, [['PGU1-54HH', 42.56], ['2xPGU1-27HH', 42.56], ['SGU2-27HH', 42.56], ['Kast', 221.94]]],
            ['4 OPzS 200', 'OPzS', 17.2, 105, 208, 420, [['-', 16.38], ['-', 16.38], ['-', 16.38], ['Kast', null]]],
            ['6 OPzS 420', 'OPzS', 31.5, 147, 208, 535, [['PGL1-39H', 16.38], ['PGL2-20H', 16.38], ['SGL2-20H', 16.38], ['Kast', null]]],
            ['7 OPzS 490', 'OPzS', 36.1, 168, 208, 535, [['-', 16.38], ['-', 16.38], ['-', 16.38], ['Kast', null]]],
            ['6 OPzS 600', 'OPzS', 44.8, 147, 208, 710, [['PGL1-39H', 16.77], ['PGL2-20H', 16.77], ['SGL2-20H', 16.77], ['Kast', null]]],
            ['8 OPzS 800', 'OPzS', 61.3, 215, 193, 710, [['PGL1-54H', 20.25], ['PGL2-27H', 20.25], ['SGL2-27H', 20.25], ['Kast', null]]],
            ['10 OPzS 1000', 'OPzS', 74.5, 215, 235, 710, [['PGS1-54HH', 24.52], ['PGS2-27HH', 24.52], ['SGS2-27HH', 24.52], ['Kast', null]]],
            ['12 OPzS 1200', 'OPzS', 88, 215, 277, 710, [['PGS1-54HH', 24.52], ['PGS2-27HH', 24.52], ['SGS2-27HH', 24.52], ['Kast', null]]],
            ['12 OPzS 1500', 'OPzS', 114.3, 215, 277, 855, [['PGS1-54HH', 25.92], ['PGS2-27HH', 25.92], ['SGS2-27HH', 25.92], ['Kast', null]]],
            ['16 OPzS 2000', 'OPzS', 151.5, 215, 400, 815, [['PGT1-54HH', 39.33], ['2xPGT1-27HH', 39.33], ['SGT2-27SH', 39.33], ['Kast', null]]],
            ['20 OPzS 2500', 'OPzS', 193, 215, 490, 815, [['PGU1-54HH', 42.43], ['2XPGU1-27HH', 42.43], ['SGU2-27HH', 42.43], ['Kast', null]]],
            ['24 OPzS 3000', 'OPzS', 234.5, 215, 580, 815, [['-', 42.43], ['-', 42.43], ['-', 42.43], ['-', null]]],
        ];

        foreach ($models as [$name, $technology, $kg, $l, $b, $h, $racks]) {
            $id = DB::table('battery_models')->insertGetId([
                'name' => $name, 'technology' => $technology, 'weight_kg' => $kg, 'length_mm' => $l, 'width_mm' => $b, 'height_mm' => $h,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach (['lijn', 'u_vorm', 'trap', 'kast'] as $index => $mounting) {
                DB::table('battery_racks')->insert([
                    'battery_model_id' => $id, 'mounting' => $mounting, 'code' => $racks[$index][0], 'price' => $racks[$index][1],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $cables = [
            ['50mm² x 0,60m', 17.51], ['50mm² x 0,65m', 18.86], ['50mm² x 0,75m', 20.2], ['50mm² x 0,80m', 21.83],
            ['70mm² x 1m', 34.49], ['70mm² x 1,25m', 33.31], ['70mm² x 1,4m', 43.57], ['70mm² x 1,65m', 48.6],
            ['70mm² x 2,2m', 59.46], ['70mm² x 3m', 85.82], ['70mm² x 6m', 171.64],
        ];

        DB::table('battery_cables')->insert(array_map(fn (array $cable): array => [
            'name' => $cable[0], 'price' => $cable[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $cables));
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_dossier_batteries');
        Schema::dropIfExists('battery_dossiers');
        Schema::dropIfExists('battery_cables');
        Schema::dropIfExists('battery_racks');
        Schema::dropIfExists('battery_models');
    }
};
