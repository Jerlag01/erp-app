<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System backups (Systeem → Back-ups): the backup targets (SFTP, SMB, Synology or a folder on the server),
 * separate from the NAS systems that keep a copy of the documents, and the log of backups and restores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->string('host');
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('share')->nullable();
            $table->string('path', 500)->nullable();
            $table->boolean('verify_tls')->default(true);
            $table->json('parts');
            $table->string('frequency', 20)->default('daily');
            $table->string('time', 5)->default('02:00');
            $table->unsignedSmallInteger('keep')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_name')->nullable();
            $table->string('kind', 20)->default('backup');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('parts')->nullable();
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('backup_targets');
    }
};
