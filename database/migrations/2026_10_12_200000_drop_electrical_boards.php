<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Electrical boards are named differently on every COW code site, so the employee types the
 * board name when closing an intervention instead of picking it from a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_report_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('electrical_board_id');
        });

        Schema::dropIfExists('electrical_boards');
    }

    public function down(): void
    {
        Schema::create('electrical_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['work_site_id', 'name']);
        });

        Schema::table('intervention_report_positions', function (Blueprint $table) {
            $table->foreignId('electrical_board_id')->nullable()->after('intervention_report_id')->constrained()->nullOnDelete();
        });
    }
};
