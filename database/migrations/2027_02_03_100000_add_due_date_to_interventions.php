<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions get the date they must be executed by at the latest; once it has passed and the
 * intervention is not executed yet, it shows in red.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('intervention_due_on')->nullable()->after('intervention_tags')->index();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['intervention_due_on']);
            $table->dropColumn('intervention_due_on');
        });
    }
};
