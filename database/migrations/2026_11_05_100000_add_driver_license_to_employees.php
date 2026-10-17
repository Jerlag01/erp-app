<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The driver license of an employee: the categories (several), the number (encrypted, like the
 * national number), until when it is valid and copies of the card (photos or PDF, private disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->json('driver_license_categories')->nullable()->after('education_level');
            $table->text('driver_license_number')->nullable()->after('driver_license_categories');
            $table->date('driver_license_expires_on')->nullable()->after('driver_license_number');
            $table->json('driver_license_files')->nullable()->after('driver_license_expires_on');
            $table->json('driver_license_file_names')->nullable()->after('driver_license_files');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['driver_license_categories', 'driver_license_number', 'driver_license_expires_on', 'driver_license_files', 'driver_license_file_names']);
        });
    }
};
