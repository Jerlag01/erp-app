<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name', 150);
            $table->timestamp('reported_at')->index();
            $table->string('type', 30)->index();
            $table->nullableMorphs('place');
            $table->string('place_label', 200)->nullable();
            $table->string('location_details', 255)->nullable();
            $table->boolean('other_victims')->default(false);
            $table->text('other_victims_details')->nullable();
            $table->boolean('material_damage')->default(false);
            $table->text('material_damage_details')->nullable();
            $table->text('description');
            $table->json('photos')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->text('follow_up')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
