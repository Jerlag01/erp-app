<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The role "Administratie": like HR, no prestation sheet (so not in the planning, vacation or
     * sickness) and no team needed. By default it sees the monthly prestations and manages all leave and
     * absences of everyone; other modules are given
     * through the role's permissions.
     */
    public function up(): void
    {
        if (! DB::table('roles')->where('name', 'Administratie')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Administratie',
                'description' => 'Administratie: eigen account en prestaties per maand; geen prestatiefiche, planning of verlof en geen ploeg nodig; andere modules via de rechten van de rol.',
                'is_admin' => false,
                'is_guest' => false,
                'no_timesheet' => true,
                'permissions' => json_encode(['timesheets' => ['see_all', 'generate_pdf'], 'vacations' => ['view', 'approve', 'manage']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Administratie')->whereNotExists(fn ($query) => $query->from('users')->whereColumn('users.role_id', 'roles.id'))->delete();
    }
};
