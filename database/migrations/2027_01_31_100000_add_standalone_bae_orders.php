<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BAE orders without a project: a simple order (extra cables, connectors… for the batteries) with free
 * lines and its own running reference (BAE-0001), next to the battery order of a 604 project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bae_orders', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('bae_orders', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->nullable()->unique()->after('project_id');
            $table->string('subject', 150)->nullable()->after('sequence');
            $table->json('items')->nullable()->after('lines');
        });
    }

    public function down(): void
    {
        Schema::table('bae_orders', function (Blueprint $table): void {
            $table->dropColumn(['sequence', 'subject', 'items']);
        });
    }
};
