<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 608 projects and charging stations can be at a third-party client with its own installation
 * address instead of a COW site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('location_type', 10)->default('cow')->after('work_site_id');
        });

        Schema::table('charging_stations', function (Blueprint $table) {
            $table->foreignId('work_site_id')->nullable()->change();
            $table->string('location_type', 10)->default('cow')->after('work_site_id');
            $table->string('client_name', 255)->nullable()->after('location_type');
            $table->string('client_company', 255)->nullable()->after('client_name');
            $table->string('client_email', 255)->nullable()->after('client_company');
            $table->string('client_phone', 50)->nullable()->after('client_email');
            $table->string('street', 150)->nullable()->after('client_phone');
            $table->string('house_number', 20)->nullable()->after('street');
            $table->string('addition', 20)->nullable()->after('house_number');
            $table->string('postal_code', 20)->nullable()->after('addition');
            $table->string('city', 100)->nullable()->after('postal_code');
            $table->foreignId('country_id')->nullable()->after('city')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charging_stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['location_type', 'client_name', 'client_company', 'client_email', 'client_phone', 'street', 'house_number', 'addition', 'postal_code', 'city']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('location_type');
        });
    }
};
