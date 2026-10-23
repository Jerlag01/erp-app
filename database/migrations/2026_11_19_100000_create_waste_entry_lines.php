<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Battery waste can have several destruction certificates, COW codes and PO numbers, each with its
 * own weight and region: they move to lines, and the weight of the entry becomes their sum. The
 * regional lists are made from the lines. Existing battery entries become one line each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_entry_id')->constrained()->cascadeOnDelete();
            $table->string('region', 20)->index();
            $table->char('destruction_certificate', 3)->nullable();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cow_code', 5)->nullable();
            $table->string('po_number', 30)->nullable();
            $table->decimal('weight_kg', 12, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (DB::table('waste_entries')->whereNotNull('region')->orderBy('id')->get() as $entry) {
            DB::table('waste_entry_lines')->insert([
                'waste_entry_id' => $entry->id,
                'region' => $entry->region,
                'destruction_certificate' => $entry->destruction_certificate,
                'work_site_id' => $entry->work_site_id,
                'cow_code' => $entry->cow_code,
                'po_number' => $entry->po_number,
                'weight_kg' => $entry->weight_kg,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('waste_entries', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropConstrainedForeignId('work_site_id');
            $table->dropColumn(['region', 'destruction_certificate', 'po_number', 'cow_code']);
        });
    }

    public function down(): void
    {
        Schema::table('waste_entries', function (Blueprint $table) {
            $table->string('region', 20)->nullable()->index()->after('processor_reference');
            $table->char('destruction_certificate', 3)->nullable()->after('region');
            $table->string('po_number', 30)->nullable()->after('destruction_certificate');
            $table->foreignId('work_site_id')->nullable()->after('po_number')->constrained()->nullOnDelete();
            $table->string('cow_code', 5)->nullable()->after('work_site_id');
        });

        // The first line of each entry goes back on the entry.
        foreach (DB::table('waste_entry_lines')->orderBy('sort')->orderBy('id')->get()->unique('waste_entry_id') as $line) {
            DB::table('waste_entries')->where('id', $line->waste_entry_id)->update([
                'region' => $line->region,
                'destruction_certificate' => $line->destruction_certificate,
                'po_number' => $line->po_number,
                'work_site_id' => $line->work_site_id,
                'cow_code' => $line->cow_code,
            ]);
        }

        Schema::dropIfExists('waste_entry_lines');
    }
};
