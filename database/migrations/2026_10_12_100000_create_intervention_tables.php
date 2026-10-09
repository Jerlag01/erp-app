<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions: projects in category 600 (any job at a COW code). The interventions module adds
 * intervention categories (PREPARE, CONNECT, DECONNECT, …), subcategories with phases and tags
 * (COLIBRI: PHASE 1/2, FRM220UB, FRM220 FIRE), electrical boards and the closing report that an
 * administrator reviews. Category 600 itself works as a normal project category without the module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_categories', function (Blueprint $table) {
            $table->id();
            // category (several per intervention) or subcategory (one, with phases and tags).
            $table->string('kind', 20)->default('category')->index();
            $table->string('name', 100);
            // Material on interventions with this category is freed instead of used (DECONNECT).
            $table->boolean('frees_material')->default(false);
            $table->json('phases')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'name']);
        });

        Schema::create('intervention_category_project', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervention_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'intervention_category_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // Work order (WO) number of an intervention, given when it is created; unique.
            $table->string('work_order_number', 30)->nullable()->unique()->after('reference');
            $table->foreignId('intervention_subcategory_id')->nullable()->after('status')->constrained('intervention_categories')->nullOnDelete();
            $table->string('intervention_phase', 50)->nullable()->after('intervention_subcategory_id');
            $table->json('intervention_tags')->nullable()->after('intervention_phase');
        });

        Schema::create('electrical_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['work_site_id', 'name']);
        });

        Schema::create('intervention_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            // pending (review pending), approved or rejected (back to the employee).
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cow_code', 5)->nullable();
            $table->string('room', 100)->nullable();
            $table->decimal('hours_worked', 6, 2)->default(0);
            $table->text('remarks')->nullable();
            // Snapshots, so the report stays as it was closed: category names and modified boards.
            $table->json('categories')->nullable();
            $table->json('modified_boards')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            // Entered by the administrator when approving; also added to the project's PO numbers.
            $table->char('po_number', 10)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('intervention_report_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            // used or freed.
            $table->string('direction', 10)->default('used');
            $table->timestamps();
        });

        Schema::create('intervention_report_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('electrical_board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('board_name', 100);
            // AC or DC, F (fuse) or Q (breaker), supply side A or B.
            $table->string('current', 2);
            $table->string('device', 1);
            $table->string('number', 20);
            $table->string('supply', 1);
            $table->timestamps();
        });

        Schema::create('intervention_report_user', function (Blueprint $table) {
            $table->foreignId('intervention_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['intervention_report_id', 'user_id']);
        });

        $now = now();

        DB::table('intervention_categories')->insert([
            ['kind' => 'category', 'name' => 'PREPARE', 'frees_material' => false, 'phases' => null, 'tags' => null, 'sort' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kind' => 'category', 'name' => 'CONNECT', 'frees_material' => false, 'phases' => null, 'tags' => null, 'sort' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kind' => 'category', 'name' => 'DECONNECT', 'frees_material' => true, 'phases' => null, 'tags' => null, 'sort' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['kind' => 'subcategory', 'name' => 'COLIBRI', 'frees_material' => false, 'phases' => json_encode(['PHASE 1', 'PHASE 2']), 'tags' => json_encode(['FRM220UB', 'FRM220 FIRE']), 'sort' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! DB::table('project_categories')->where('code', '600')->exists()) {
            DB::table('project_categories')->insert([
                'code' => '600',
                'name' => 'Interventies',
                'description' => null,
                'numbering' => 'intervention',
                'has_short_description' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_report_user');
        Schema::dropIfExists('intervention_report_positions');
        Schema::dropIfExists('intervention_report_materials');
        Schema::dropIfExists('intervention_reports');
        Schema::dropIfExists('electrical_boards');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intervention_subcategory_id');
            $table->dropUnique(['work_order_number']);
            $table->dropColumn(['work_order_number', 'intervention_phase', 'intervention_tags']);
        });
        Schema::dropIfExists('intervention_category_project');
        Schema::dropIfExists('intervention_categories');
        DB::table('project_categories')->where('code', '600')->whereNotExists(fn ($query) => $query->from('projects')->whereColumn('projects.project_category_id', 'project_categories.id'))->delete();
    }
};
