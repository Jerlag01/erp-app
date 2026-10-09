<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planning: per employee, items in a timeslot on a day. An item is a (open) project, a warehouse job
 * with a description and task list, or something else (with the right permission).
 * Planning shares let an employee show their planning to colleagues outside their teams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            // project, warehouse or other.
            $table->string('kind', 20)->index();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // [{"text": "...", "done": false}, ...]
            $table->json('tasks')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['date', 'start_time']);
        });

        // "user_id lets viewer_id see their planning."
        Schema::create('planning_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'viewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_shares');
        Schema::dropIfExists('planning_items');
    }
};
