<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Battery moves on 605 projects (kind "move"): batteries taken from one COW site and placed at another,
 * with the vertical transport at the pickup, the shipping note of the move, and whether the first site
 * is decommissioned once the work is finished (then its building type changes, logged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->foreignId('move_from_work_site_id')->nullable()->after('project_id')->constrained('work_sites')->nullOnDelete();
            $table->foreignId('move_to_work_site_id')->nullable()->after('move_from_work_site_id')->constrained('work_sites')->nullOnDelete();
            $table->decimal('vertical_pickup_m', 8, 2)->default(0)->after('vertical_install_m');
            $table->unsignedInteger('move_packages')->default(0)->after('packages');
            $table->decimal('move_weight_kg', 10, 2)->nullable()->after('move_packages');
            $table->boolean('decommission_source')->default(false)->after('move_weight_kg');
            $table->timestamp('decommissioned_at')->nullable()->after('decommission_source');
        });
    }

    public function down(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('move_from_work_site_id');
            $table->dropConstrainedForeignId('move_to_work_site_id');
            $table->dropColumn(['vertical_pickup_m', 'move_packages', 'move_weight_kg', 'decommission_source', 'decommissioned_at']);
        });
    }
};
