<?php

use App\Actions\Gpw\SyncGpwStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A GPW status set by an administrator is no longer changed automatically; the requests that follow their
 * project get their status (planned, in progress, executed) right away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->timestamp('status_locked_at')->nullable()->after('planned_by');
            $table->foreignId('status_locked_by')->nullable()->after('status_locked_at')->constrained('users')->nullOnDelete();
        });

        app(SyncGpwStatus::class)->all();
    }

    public function down(): void
    {
        Schema::table('gpw_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_locked_by');
            $table->dropColumn('status_locked_at');
        });
    }
};
