<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who put a line on a project's part list: employees add the items they used from the mobile app and
 * may only remove their own lines there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_parts', function (Blueprint $table): void {
            $table->foreignId('added_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_parts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('added_by');
        });
    }
};
