<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The written signature on the delivery note of a battery room (as on the 604 documents): once signed,
 * the delivery note is locked for everyone but administrators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_room_dossiers', function (Blueprint $table) {
            $table->string('signer_name', 150)->nullable()->after('signed_by');
            $table->string('signature_path')->nullable()->after('signer_name');
            $table->foreignId('signature_by')->nullable()->after('signature_path')->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable()->after('signature_by');
        });
    }

    public function down(): void
    {
        Schema::table('battery_room_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_by');
            $table->dropColumn(['signer_name', 'signature_path', 'signed_at']);
        });
    }
};
