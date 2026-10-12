<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yearly control, filled in by the team: a free remark on the list, the model / serial number they
 * add to equipment that had none, and equipment they have but that is not on the list yet (a
 * proposal an administrator confirms before it becomes equipment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_checks', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('year');
            $table->foreignId('remarks_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('remarks_at')->nullable()->after('remarks_by');
        });

        // What the team filled in where the equipment had no model / serial number.
        Schema::table('asset_check_items', function (Blueprint $table) {
            $table->string('model', 100)->nullable()->after('remark');
            $table->string('serial_number', 100)->nullable()->after('model');
        });

        Schema::create('asset_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand', 60)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('result', 20)->nullable();
            $table->string('remark')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            // The equipment created on approval.
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_proposals');

        Schema::table('asset_check_items', function (Blueprint $table) {
            $table->dropColumn(['model', 'serial_number']);
        });

        Schema::table('asset_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remarks_by');
            $table->dropColumn(['remarks', 'remarks_at']);
        });
    }
};
