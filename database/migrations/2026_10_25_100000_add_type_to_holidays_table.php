<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide days off are either public holidays or "inhaalrust" (IR) days: extra days off planned
 * for all employees, outside vacation and leave. Both are no working days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('type', 20)->default('holiday')->after('date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
