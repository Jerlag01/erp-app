<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Employees can be a mentor ("peter/meter") for new colleagues, shown in the training overview.
 * - Every user arranges their own dashboard per panel: {"app": {"columns": 2, "widgets": [...], "counters": [...]}}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_mentor')->default(false)->after('contract_term');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_mentor');
        });
    }
};
