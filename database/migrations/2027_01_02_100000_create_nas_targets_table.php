<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several NAS systems, for redundancy: each one gets its own copy of every file (Admin > NAS systems).
 * The single NAS of Settings > NAS becomes the first one; every waiting copy belongs to one NAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nas_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->string('driver', 10)->default('synology');
            $table->string('url')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('share', 100)->nullable();
            $table->string('domain', 100)->nullable();
            $table->string('username', 100)->nullable();
            // Encrypted (cast "encrypted").
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->string('root')->default('/ERP');
            $table->boolean('ftps')->default(true);
            $table->boolean('passive')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->timestamps();
        });

        Schema::table('nas_sync_items', function (Blueprint $table) {
            $table->foreignId('nas_target_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // The NAS of the settings becomes the first one (its password was encrypted the same way).
        $old = json_decode((string) DB::table('settings')->where('key', 'nas')->value('value'), true) ?: [];

        if (filled($old['url'] ?? null) || filled($old['host'] ?? null)) {
            $id = DB::table('nas_targets')->insertGetId([
                'name' => 'NAS',
                'is_active' => (bool) ($old['enabled'] ?? false),
                'driver' => $old['driver'] ?? 'synology',
                'url' => $old['url'] ?? null,
                'host' => $old['host'] ?? null,
                'port' => $old['port'] ?? null,
                'share' => $old['share'] ?? null,
                'domain' => $old['domain'] ?? null,
                'username' => $old['username'] ?? null,
                'password' => $old['password'] ?? null,
                'private_key' => $old['private_key'] ?? null,
                'root' => $old['root'] ?? '/ERP',
                'ftps' => (bool) ($old['ftps'] ?? true),
                'passive' => (bool) ($old['passive'] ?? true),
                'verify_ssl' => (bool) ($old['verify_ssl'] ?? true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nas_sync_items')->orderBy('id')->each(fn (object $item) => DB::table('nas_sync_items')->where('id', $item->id)->update([
                'nas_target_id' => $id,
                'target_hash' => hash('sha256', $id.'|'.$item->target),
            ]));
        }

        DB::table('nas_sync_items')->whereNull('nas_target_id')->delete();
        DB::table('settings')->where('key', 'nas')->delete();
    }

    public function down(): void
    {
        Schema::table('nas_sync_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nas_target_id');
        });

        Schema::dropIfExists('nas_targets');
    }
};
