<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The PDF belongs to each battery line (destruction certificate), not to the waste entry as a whole.
 * A PDF already on an entry moves to its first line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_entry_lines', function (Blueprint $table) {
            $table->string('pdf_path', 500)->nullable()->after('weight_kg');
            $table->string('pdf_name', 255)->nullable()->after('pdf_path');
        });

        foreach (DB::table('waste_entries')->whereNotNull('pdf_path')->get(['id', 'pdf_path', 'pdf_name']) as $entry) {
            $line = DB::table('waste_entry_lines')->where('waste_entry_id', $entry->id)->orderBy('sort')->orderBy('id')->first();

            if ($line) {
                DB::table('waste_entry_lines')->where('id', $line->id)->update(['pdf_path' => $entry->pdf_path, 'pdf_name' => $entry->pdf_name]);
            }
        }

        Schema::table('waste_entries', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_name']);
        });
    }

    public function down(): void
    {
        Schema::table('waste_entries', function (Blueprint $table) {
            $table->string('pdf_path', 500)->nullable()->after('notes');
            $table->string('pdf_name', 255)->nullable()->after('pdf_path');
        });

        Schema::table('waste_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_name']);
        });
    }
};
