<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A COW site can have several direct contact persons (besides the contacts of its area): the single
 * work_sites.work_site_contact_id becomes the pivot work_site_work_site_contact. A GPW request keeps the
 * contact persons it was sent to (contact_ids), chosen when requesting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_site_work_site_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_site_contact_id')->constrained()->cascadeOnDelete();
            $table->unique(['work_site_id', 'work_site_contact_id'], 'work_site_contact_unique');
        });

        DB::table('work_sites')->whereNotNull('work_site_contact_id')->orderBy('id')->each(function (object $site): void {
            DB::table('work_site_work_site_contact')->insert(['work_site_id' => $site->id, 'work_site_contact_id' => $site->work_site_contact_id]);
        });

        Schema::table('work_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_site_contact_id');
        });

        Schema::table('gpw_requests', function (Blueprint $table) {
            $table->json('contact_ids')->nullable()->after('work_site_contact_id');
        });

        // Requests made before: the contact they name.
        DB::table('gpw_requests')->whereNotNull('work_site_contact_id')->update(['contact_ids' => DB::raw('JSON_ARRAY(work_site_contact_id)')]);
    }

    public function down(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table) {
            $table->dropColumn('contact_ids');
        });

        Schema::table('work_sites', function (Blueprint $table) {
            $table->foreignId('work_site_contact_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('work_site_work_site_contact')->orderBy('id')->each(function (object $row): void {
            DB::table('work_sites')->where('id', $row->work_site_id)->whereNull('work_site_contact_id')->update(['work_site_contact_id' => $row->work_site_contact_id]);
        });

        Schema::dropIfExists('work_site_work_site_contact');
    }
};
