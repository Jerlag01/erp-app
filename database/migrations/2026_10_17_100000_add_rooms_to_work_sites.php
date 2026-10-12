<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms ("lokalen") of EC, LDC and Colibri sites, e.g. ["A00A007", "B01"].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_sites', function (Blueprint $table) {
            $table->json('rooms')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('work_sites', function (Blueprint $table) {
            $table->dropColumn('rooms');
        });
    }
};
