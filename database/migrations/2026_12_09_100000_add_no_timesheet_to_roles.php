<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles without a prestation sheet (e.g. HR): their users are not in the timesheets, the planning,
     * vacation or sickness. The role "HR" comes with the monthly prestations overview and the HR calculations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('no_timesheet')->default(false)->after('is_guest');
        });

        if (! DB::table('roles')->where('name', 'HR')->exists()) {
            DB::table('roles')->insert([
                'name' => 'HR',
                'description' => 'Personeelsdienst: eigen account, prestaties per maand en HR-berekeningen; geen prestatiefiche, planning of verlof.',
                'is_admin' => false,
                'is_guest' => false,
                'no_timesheet' => true,
                'permissions' => json_encode(['timesheets' => ['see_all', 'generate_pdf'], 'hr_calculations' => ['view', 'manage']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('no_timesheet');
        });
    }
};
