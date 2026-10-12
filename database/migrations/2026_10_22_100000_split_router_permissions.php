<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 4G routers get their own permissions ("routers"), next to "charging" for the charging stations.
 * Roles keep what they had: their "charging" rights are copied to "routers". Administrator roles get
 * nothing: "routers.secrets" has to be ticked on them explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('is_admin', false)->where('is_guest', false)->orderBy('id')->each(function (object $role): void {
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (! empty($permissions['charging']) && empty($permissions['routers'])) {
                $permissions['routers'] = $permissions['charging'];
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (array_key_exists('routers', $permissions)) {
                unset($permissions['routers']);
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }
};
