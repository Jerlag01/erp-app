<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos of the completed works, attached by the technician as the last step of a battery dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->json('photos')->nullable();
            $table->text('photos_remark')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->dropColumn(['photos', 'photos_remark']);
        });
    }
};
