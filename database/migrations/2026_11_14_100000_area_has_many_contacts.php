<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A COW-code area can have several contact persons; the existing contact of each area is kept.
 * GPW requests can go to all of them, so their recipient fields hold more than one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_site_area_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['work_site_area_id', 'work_site_contact_id'], 'area_contact_unique');
        });

        DB::table('work_site_areas')->whereNotNull('work_site_contact_id')->orderBy('id')->each(function (object $area): void {
            DB::table('work_site_area_contact')->insert([
                'work_site_area_id' => $area->id,
                'work_site_contact_id' => $area->work_site_contact_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('work_site_areas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_site_contact_id');
        });

        Schema::table('gpw_requests', function (Blueprint $table) {
            $table->string('contact_name', 500)->change();
            $table->string('contact_email', 1000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_site_areas', function (Blueprint $table) {
            $table->foreignId('work_site_contact_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        foreach (DB::table('work_site_area_contact')->orderBy('id')->get() as $link) {
            DB::table('work_site_areas')->where('id', $link->work_site_area_id)->whereNull('work_site_contact_id')->update(['work_site_contact_id' => $link->work_site_contact_id]);
        }

        Schema::dropIfExists('work_site_area_contact');
    }
};
