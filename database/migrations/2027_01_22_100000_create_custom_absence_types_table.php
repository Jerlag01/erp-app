<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absence types added by administration themselves, each with its own short code for the prestation
 * overviews and how the prestation sheet counts it; an absence of such a type has type "custom".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_absence_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('name', 100);
            $table->string('name_fr', 100)->nullable();
            $table->string('name_en', 100)->nullable();
            $table->string('color', 7)->default('#64748b');
            $table->string('counting', 20)->default('unworked');
            $table->boolean('blocks_planning')->default(false);
            $table->boolean('is_private')->default(true);
            $table->boolean('takes_document')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->foreignId('custom_absence_type_id')->nullable()->after('type')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_absence_type_id');
        });

        Schema::dropIfExists('custom_absence_types');
    }
};
