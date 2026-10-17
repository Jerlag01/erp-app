<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime taken as paid leave is requested and approved like vacation: a request of type "overtime".
 * Once approved it becomes an overtime absence (absence_id), which the timesheets count as "R".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table): void {
            $table->string('type', 20)->default('vacation')->after('user_id')->index();
            $table->foreignId('absence_id')->nullable()->after('review_note')->constrained('absences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('absence_id');
            $table->dropColumn('type');
        });
    }
};
