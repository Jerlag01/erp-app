<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly prestation sheets of interims go to their interim office only after someone with
 * "timesheets.verify_interim" (administrators and the role Administratie) verified them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_weeks', function (Blueprint $table) {
            // The office the sheet waits for (set when the week is sent).
            $table->foreignId('interim_office_id')->nullable()->after('pdf_path')->constrained()->nullOnDelete();
            $table->timestamp('interim_verified_at')->nullable()->after('interim_office_id');
            $table->foreignId('interim_verified_by')->nullable()->after('interim_verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('interim_sent_at')->nullable()->after('interim_verified_by');
        });

        // The role Administratie verifies them.
        $role = DB::table('roles')->where('name', 'Administratie')->first();

        if ($role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $permissions['timesheets'] = array_values(array_unique([...($permissions['timesheets'] ?? []), 'verify_interim']));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::table('timesheet_weeks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interim_verified_by');
            $table->dropConstrainedForeignId('interim_office_id');
            $table->dropColumn(['interim_verified_at', 'interim_sent_at']);
        });
    }
};
