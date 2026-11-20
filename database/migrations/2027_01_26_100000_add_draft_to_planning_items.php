<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft planning: a temporary planning between the project and the real planning. A draft is not
 * sent to the employee (no app, push, mail or Outlook) until someone with "planning.confirm"
 * confirms it. Everything planned before stays confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_items', function (Blueprint $table): void {
            $table->boolean('is_draft')->default(false)->after('kind')->index();
            $table->timestamp('confirmed_at')->nullable()->after('is_draft');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planning_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['is_draft', 'confirmed_at']);
        });
    }
};
