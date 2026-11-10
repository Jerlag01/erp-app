<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per area a separate list of contact persons for interventions (600, 608.1): they are offered for the GPW
 * requests of interventions only, not for other projects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_site_area_intervention_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['work_site_area_id', 'work_site_contact_id'], 'area_intervention_contact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_site_area_intervention_contact');
    }
};
