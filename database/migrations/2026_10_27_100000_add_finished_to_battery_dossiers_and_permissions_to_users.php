<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - A battery dossier is finished by the technician (after the photos): the 604 project goes to
 *   "invoicing to do" and the dossier is locked, except for "batteries.edit_finished".
 * - Users can get their own permissions per area, which overrule those of their role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('finished_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            // {"area": ["ability", ...]}: per area replaces the role's abilities (an empty list takes them all away).
            $table->json('permissions')->nullable()->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });

        Schema::table('battery_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finished_by');
            $table->dropColumn('finished_at');
        });
    }
};
