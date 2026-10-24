<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the project went to "executed" (shown to the client with the battery deliveries). Projects that
 * are already executed or further get their last change as best guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('executed_at')->nullable()->after('status');
        });

        DB::table('projects')
            ->whereIn('status', ['executed', 'invoicing_to_do', 'invoiced'])
            ->update(['executed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('executed_at');
        });
    }
};
