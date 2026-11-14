<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offers (quotes) for projects: categories (A, C, E, G, …), default lines with a supply and an
 * installation price, and the offers with their lines. Starts with the categories and lines of the
 * Proximus offer template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5);
            $table->string('name');
            // General costs (study, visit, travel): outside the price of the works, added to the total.
            $table->boolean('is_general_costs')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('standard_offer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_category_id')->constrained('offer_categories')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('supply_price', 12, 2)->nullable();
            $table->decimal('install_price', 12, 2)->nullable();
            // Labour: the installation price is the hourly rate of the offer.
            $table->boolean('is_labour')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->unsignedInteger('number_sequence')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('offer_date');
            $table->date('valid_until')->nullable();
            $table->string('subject')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('our_reference')->nullable();
            $table->text('site_address')->nullable();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('hours_per_day', 4, 2)->default(8);
            $table->text('remarks')->nullable();
            $table->decimal('supply_total', 12, 2)->default(0);
            $table->decimal('install_total', 12, 2)->default(0);
            $table->decimal('general_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('offer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_category_id')->nullable()->constrained('offer_categories')->nullOnDelete();
            $table->string('description');
            // Empty quantity: a text line (e.g. "Overschakeling van verbruikers").
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('supply_price', 12, 2)->nullable();
            $table->decimal('install_price', 12, 2)->nullable();
            $table->decimal('supply_total', 12, 2)->default(0);
            $table->decimal('install_total', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_lines');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('standard_offer_lines');
        Schema::dropIfExists('offer_categories');
    }

    /**
     * The categories and lines of the Proximus offer template, to change in Finance > Offer categories / Default offer lines.
     */
    protected function seedDefaults(): void
    {
        $now = now();
        $categories = [
            ['A', 'Plaatsen omvormer', false, [
                ['PDU 9x(2P+A)16A', 'st', 53.96, null],
                ['DC-AC power inverter TS-400-248B (400W)', 'st', 362.79, null],
                ['Fixed shelf 19", 445x240mm, 2HU, Black Ref: 50578', 'st', 33.61, null],
                ['DIN-rail 50cm + accessoires', 'st', 7.95, null],
            ]],
            ['C', 'Bekabeling AC & DC', false, [
                ['XGB 3G2,5mm² Cca', 'm', 2.80, null],
                ['KB - omvormer 10A Jean-Müller T00 gG', 'st', null, 14.83],
                ['HO7Z1-K 16mm² Aarding', 'm', 5.58, null],
                ['Labelling kabels (forfait per kabel)', 'st', 5.00, null],
                ['Labelling racks (forfait per rack)', 'st', 5.00, null],
                ['Overschakeling van verbruikers (per uur)', 'u', null, null, true],
            ]],
            ['E', 'Overnameprijs demontage materiaal', false, [
                ['Minprijs demontage materiaal', 'st', -5.00, null],
            ]],
            ['G', 'Algemene onkosten', true, [
                ['Studie, bezoek, verplaatsing, plannen', 'st', 196.60, null],
            ]],
        ];

        foreach ($categories as $index => [$code, $name, $general, $lines]) {
            $id = DB::table('offer_categories')->insertGetId([
                'code' => $code, 'name' => $name, 'is_general_costs' => $general, 'is_active' => true, 'sort_order' => $index + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($lines as $sort => $line) {
                DB::table('standard_offer_lines')->insert([
                    'offer_category_id' => $id, 'description' => $line[0], 'quantity' => 1, 'unit' => $line[1],
                    'supply_price' => $line[2], 'install_price' => $line[3], 'is_labour' => $line[4] ?? false,
                    'is_active' => true, 'sort_order' => $sort + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
};
