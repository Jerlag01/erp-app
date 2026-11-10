<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The copy of the files on the NAS (Synology), for redundancy: every file or PDF that has to be (re)written
 * there is an item of this outbox, sent after the response and retried by "nas:sync". The employee gets
 * an upload for their identity card (mirrored with their other documents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nas_sync_items', function (Blueprint $table) {
            $table->id();
            // The path on the NAS below the root folder, e.g. "604/604-0012-02GAM/Foto's/kast.jpg".
            $table->string('target', 700);
            $table->char('target_hash', 64)->unique();
            // {"disk": "local", "path": "…"} for a stored file, {"pdf": "employee_info", "id": 12} for a PDF.
            $table->json('source');
            $table->string('status', 10)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->json('id_card_files')->nullable()->after('driver_license_file_names');
            $table->json('id_card_file_names')->nullable()->after('id_card_files');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nas_sync_items');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['id_card_files', 'id_card_file_names']);
        });
    }
};
