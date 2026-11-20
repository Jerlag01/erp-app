<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "No GPW = no planning": a project (not an intervention) can only be planned once a GPW request is
 * approved. Every planning without one needs a reason (not with "planning.bypass_gpw") and is logged on
 * the project with what was planned. Projects already in the planning stay plannable (planning_unlocked_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('planning_unlocked_at')->nullable();
        });

        Schema::create('project_gpw_bypasses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('planned')->nullable();
            $table->timestamps();
        });

        DB::table('projects')
            ->whereIn('id', DB::table('planning_items')->whereNotNull('project_id')->select('project_id'))
            ->update(['planning_unlocked_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_gpw_bypasses');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('planning_unlocked_at');
        });
    }
};
