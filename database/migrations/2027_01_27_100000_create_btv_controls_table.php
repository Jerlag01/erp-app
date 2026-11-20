<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTV controls (inspections by the BTV body), followed apart from the as-builts: for which project,
 * when it was requested, the date BTV confirmed, and the report, which goes into the project's
 * files under "BTV control".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('btv_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('requested_on')->index();
            $table->date('confirmed_on')->nullable()->index();
            $table->foreignId('project_file_id')->nullable()->constrained('project_files')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('btv_controls');
    }
};
