<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The yearly equipment control: per team and year a list of the team's equipment that the team
 * members mark OK / not OK. Only visible to the team while it is open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when closed: the list is then hidden from the team again.
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'year']);
        });

        Schema::create('asset_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('result', 20)->nullable();
            $table->string('remark')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_check_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_check_items');
        Schema::dropIfExists('asset_checks');
    }
};
