<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The waste registry: categories and subcategories with their EURAL waste code, the waste
 * processors waste goes to, and the registry entries. Battery entries also carry the region,
 * the certificate of destruction number, the COW code and the PO number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('waste_categories')->restrictOnDelete();
            $table->string('name');
            // EURAL code, e.g. "16 06 01*" (the asterisk marks hazardous waste).
            $table->string('waste_code', 20)->nullable();
            $table->boolean('is_batteries')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('waste_processors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vat_number', 30)->nullable();
            $table->string('permit_number', 100)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('waste_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('waste_category_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 12, 2);
            $table->foreignId('waste_processor_id')->constrained()->restrictOnDelete();
            $table->string('processor_reference', 100)->nullable();
            // Batteries only.
            $table->string('region', 20)->nullable()->index();
            $table->char('destruction_certificate', 3)->nullable();
            $table->string('po_number', 30)->nullable();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cow_code', 5)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $order = 0;

        // A starting list of what an electrical contractor typically disposes of; administrators can change it.
        $categories = [
            ['Batterijen', null, true, [
                ['Loodaccu\'s', '16 06 01*'],
                ['Nikkel-cadmiumbatterijen', '16 06 02*'],
                ['Alkalinebatterijen', '16 06 04'],
                ['Lithium- en andere batterijen', '16 06 05'],
                ['Gemengde batterijen (inzameling)', '20 01 33*'],
            ]],
            ['Kabels', null, false, [
                ['Kabels zonder gevaarlijke stoffen', '17 04 11'],
                ['Kabels met olie, koolteer of gevaarlijke stoffen', '17 04 10*'],
            ]],
            ['Metalen', null, false, [
                ['Koper, brons, messing', '17 04 01'],
                ['Aluminium', '17 04 02'],
                ['IJzer en staal', '17 04 05'],
                ['Gemengde metalen', '17 04 07'],
            ]],
            ['Elektrisch en elektronisch afval (AEEA)', null, false, [
                ['Afgedankte apparatuur', '16 02 14'],
                ['Apparatuur met gevaarlijke onderdelen', '16 02 13*'],
                ['TL-buizen en lampen met kwik', '20 01 21*'],
            ]],
            ['Verpakkingen', null, false, [
                ['Papier en karton', '15 01 01'],
                ['Kunststof verpakkingen', '15 01 02'],
                ['Houten verpakkingen (paletten, haspels)', '15 01 03'],
                ['Verpakkingen met resten van gevaarlijke stoffen', '15 01 10*'],
            ]],
            ['Bouw- en sloopafval', null, false, [
                ['Hout', '17 02 01'],
                ['Kunststof', '17 02 03'],
                ['Gemengd bouw- en sloopafval', '17 09 04'],
            ]],
            ['Absorbentia, poetsdoeken en filters (verontreinigd)', '15 02 02*', false, []],
            ['Bedrijfsrestafval', '20 03 01', false, []],
        ];

        foreach ($categories as [$name, $code, $isBatteries, $children]) {
            $parentId = DB::table('waste_categories')->insertGetId([
                'name' => $name, 'waste_code' => $code, 'is_batteries' => $isBatteries, 'is_active' => true,
                'sort_order' => $order++, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($children as $index => [$childName, $childCode]) {
                DB::table('waste_categories')->insert([
                    'parent_id' => $parentId, 'name' => $childName, 'waste_code' => $childCode, 'is_batteries' => $isBatteries,
                    'is_active' => true, 'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_entries');
        Schema::dropIfExists('waste_processors');
        Schema::dropIfExists('waste_categories');
    }
};
