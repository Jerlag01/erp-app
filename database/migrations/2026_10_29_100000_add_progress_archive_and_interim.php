<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Progress steps of the 610–650 projects (Proximus charging stations), ticked by hand or automatically.
 * - Closed projects are archived with a ZIP of all their files.
 * - A project can be a copy of another one, or the follow-up (605) of a 604 project.
 * - Interim employees (working for us through an interim office).
 * - Category 608.1: interventions on charging stations, with the intervention workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            // Null when done without a known date (e.g. ticked automatically).
            $table->date('completed_on')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('automatic')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'step']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('archive_path')->nullable();
            $table->foreignId('source_project_id')->nullable()->constrained('projects')->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_interim')->default(false)->after('is_mentor');
            $table->string('interim_agency', 150)->nullable()->after('is_interim');
        });

        if (! DB::table('project_categories')->where('code', '608.1')->exists()) {
            DB::table('project_categories')->insert([
                'code' => '608.1',
                'name' => 'Interventies laadpalen',
                'description' => null,
                'numbering' => 'intervention',
                'has_short_description' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('project_categories')->where('code', '608.1')->whereNotExists(fn ($query) => $query->from('projects')->whereColumn('projects.project_category_id', 'project_categories.id'))->delete();

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['is_interim', 'interim_agency']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_project_id');
            $table->dropColumn(['archived_at', 'archive_path']);
        });

        Schema::dropIfExists('project_steps');
    }
};
