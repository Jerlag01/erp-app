<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several projects in one GPW request mail: each project keeps its own request (and period), the requests
 * asked together share a batch, are mailed once and decided together from that mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->string('batch_id', 26)->nullable()->after('project_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
