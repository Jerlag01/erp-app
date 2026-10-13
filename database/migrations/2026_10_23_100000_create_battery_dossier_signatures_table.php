<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The written signature on the delivery note, measurement report and visual inspection report of a
 * battery dossier. Once signed, that part of the dossier is locked; only administrators change it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_dossier_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battery_dossier_id')->constrained()->cascadeOnDelete();
            $table->string('document', 30);
            $table->string('signer_name', 150);
            // PNG of the drawn signature on the private disk.
            $table->string('path');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->unique(['battery_dossier_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_dossier_signatures');
    }
};
