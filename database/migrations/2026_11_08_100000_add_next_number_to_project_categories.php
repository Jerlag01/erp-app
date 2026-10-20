<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * From which follow-up number new projects of a category count on (numbers in use are skipped).
 * Empty: the number after the highest one, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table): void {
            $table->unsignedInteger('next_number')->nullable()->after('numbering');
        });
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table): void {
            $table->dropColumn('next_number');
        });
    }
};
