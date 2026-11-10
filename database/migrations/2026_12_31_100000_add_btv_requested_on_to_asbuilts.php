<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day the Normec BTV inspection was requested (set at any moment, apart from the planned btv_date);
 * shown to the client per project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asbuilts', function (Blueprint $table) {
            $table->date('btv_requested_on')->nullable()->after('btv_date');
        });
    }

    public function down(): void
    {
        Schema::table('asbuilts', function (Blueprint $table) {
            $table->dropColumn('btv_requested_on');
        });
    }
};
