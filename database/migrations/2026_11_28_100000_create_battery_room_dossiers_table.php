<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Battery rooms (609): after batteries were replaced (604) in Flanders or Brussels, the battery room is
 * brought up to standard. One dossier per 609 project, from the Excel "Leveringsnota 14EIN": the
 * delivery note (quantities per item), extra info from the client, the checklist (OK/NOK and action per
 * point) and uploads (photos, ventilation calculation, BTV report).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('project_categories')->where('code', '609')->exists()) {
            DB::table('project_categories')->insert([
                'code' => '609',
                'name' => 'Batterijlokalen',
                'description' => null,
                'numbering' => 'sequence',
                'has_short_description' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('battery_room_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            // nl or fr; empty: the language of the region of the COW site.
            $table->string('document_locale', 5)->nullable();
            $table->string('room_number', 50)->nullable();
            // Delivery note.
            $table->date('note_date')->nullable();
            $table->string('client_reference', 50)->nullable();
            // {"lighting": {"tube": 2}, "emergency_light": {"light": 1}, …}
            $table->json('quantities')->nullable();
            $table->text('remarks')->nullable();
            $table->string('signed_by', 100)->nullable();
            // Extra info from the client.
            $table->text('extra_info')->nullable();
            // Checklist: {"lighting": {"ok": true, "action": "…"}, …}
            $table->date('checklist_date')->nullable();
            $table->json('checklist')->nullable();
            // Uploads.
            $table->json('photos')->nullable();
            $table->string('ventilation_file')->nullable();
            $table->string('ventilation_file_name')->nullable();
            $table->string('btv_file')->nullable();
            $table->string('btv_file_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_room_dossiers');
    }
};
