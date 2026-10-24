<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Battery lines get a project reference (a project of the platform, or typed in freely); battery
 * entries get their PDF (e.g. the consignment note), added behind the regional list in the export.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_entry_lines', function (Blueprint $table) {
            $table->string('project_reference', 60)->nullable()->after('po_number');
            $table->foreignId('project_id')->nullable()->after('project_reference')->constrained()->nullOnDelete();
        });

        Schema::table('waste_entries', function (Blueprint $table) {
            $table->string('pdf_path', 500)->nullable()->after('notes');
            $table->string('pdf_name', 255)->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('waste_entries', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_name']);
        });

        Schema::table('waste_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('project_reference');
        });
    }
};
