<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an as-built came to be: automatically when the project was executed, or by hand with the
 * button on the project (and by whom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asbuilts', function (Blueprint $table) {
            $table->string('origin', 20)->default('automatic')->after('status');
            $table->foreignId('created_by')->nullable()->after('origin')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asbuilts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('origin');
        });
    }
};
