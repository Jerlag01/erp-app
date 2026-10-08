<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // sequence: NNN + COW code, yearly: YYNNN + COW code, private: NNN with client details.
            $table->string('numbering', 20)->default('sequence');
            $table->boolean('has_short_description')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // Restrict: a category cannot be deleted while it holds projects.
            $table->foreignId('project_category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('reference', 40)->index();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot, so the reference survives when the work site is removed.
            $table->string('cow_code', 5)->nullable();
            $table->string('short_description')->nullable();
            $table->string('status', 30)->default('offer')->index();
            $table->string('client_name')->nullable();
            $table->string('client_company')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone', 50)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('addition', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_category_id', 'number']);
        });

        Schema::create('project_po_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->char('number', 10)->index();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('project_leaders', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('project_team', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'team_id']);
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'section']);
        });

        Schema::create('project_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['project_file_id', 'version']);
        });

        Schema::create('project_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the item name, or a free description for something that is not in stock.
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        $categories = [
            ['604', 'sequence', false], ['605', 'yearly', false], ['606', 'private', false],
            ['607', 'sequence', true], ['608', 'sequence', true], ['610', 'sequence', true], ['620', 'sequence', true],
            ['630', 'sequence', true], ['640', 'sequence', true], ['650', 'sequence', true],
        ];

        // Names and descriptions are placeholders: administrators fill them in under Projects > Categories.
        DB::table('project_categories')->insert(array_map(fn (array $category): array => [
            'code' => $category[0],
            'name' => $category[0],
            'description' => null,
            'numbering' => $category[1],
            'has_short_description' => $category[2],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $categories));
    }

    public function down(): void
    {
        Schema::dropIfExists('project_parts');
        Schema::dropIfExists('project_file_versions');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('project_team');
        Schema::dropIfExists('project_leaders');
        Schema::dropIfExists('project_po_numbers');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_categories');
    }
};
