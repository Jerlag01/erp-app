<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cable calculation of a project keeps its PDF in the project's extra files and in the "cable dimensions"
 * of the project's as-built (see CableCalculationDocuments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->foreignId('project_file_id')->nullable()->after('project_id')->constrained('project_files')->nullOnDelete();
            $table->foreignId('asbuilt_file_id')->nullable()->after('project_file_id')->constrained('asbuilt_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cable_calculations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asbuilt_file_id');
            $table->dropConstrainedForeignId('project_file_id');
        });
    }
};
