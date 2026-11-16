<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One intervention (one WO/PO) can cover several COW codes: the extra COW codes of the project, each with
 * its own intervention report. A report is therefore no longer unique per project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_intervention_work_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'work_site_id']);
        });

        Schema::table('intervention_reports', function (Blueprint $table) {
            // The foreign key needs an index of its own before the unique one goes.
            $table->index('project_id');
        });

        Schema::table('intervention_reports', function (Blueprint $table) {
            $table->dropUnique(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_intervention_work_site');
    }
};
