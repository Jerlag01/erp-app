<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The client portal's new tab "Battery dossiers (604)": client users who see projects get it, so it
 * shows up at once (it can be unticked per client user).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('client_users')->orderBy('id')->each(function (object $user): void {
            $permissions = json_decode((string) $user->permissions, true) ?: [];

            if (in_array('projects', $permissions, true) && ! in_array('battery_reports', $permissions, true)) {
                $permissions[] = 'battery_reports';
                DB::table('client_users')->where('id', $user->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        });
    }

    public function down(): void
    {
        DB::table('client_users')->orderBy('id')->each(function (object $user): void {
            $permissions = array_values(array_diff(json_decode((string) $user->permissions, true) ?: [], ['battery_reports']));
            DB::table('client_users')->where('id', $user->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
