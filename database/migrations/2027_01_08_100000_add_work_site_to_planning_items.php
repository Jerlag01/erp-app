<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site (COW code) a planning item goes to, for a battery move (605): the move's origin or
 * destination. Empty means the project's own site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_items', function (Blueprint $table): void {
            $table->foreignId('work_site_id')->nullable()->after('project_id')->constrained('work_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planning_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_site_id');
        });
    }
};
