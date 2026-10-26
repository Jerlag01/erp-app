<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cable section calculations (indication after IEC 60364-5-52): the circuit, the cable and how it
     * is laid, and the result at the moment it was saved.
     */
    public function up(): void
    {
        Schema::create('cable_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('system', 10);
            $table->decimal('voltage', 8, 1);
            $table->string('load_type', 10)->default('current');
            $table->decimal('load_current', 10, 2)->nullable();
            $table->decimal('load_power', 10, 2)->nullable();
            $table->decimal('power_factor', 4, 2)->nullable();
            $table->decimal('length', 10, 1);
            $table->string('material', 5);
            $table->string('insulation', 5);
            $table->string('installation_method', 5);
            $table->smallInteger('ambient_temperature');
            $table->unsignedSmallInteger('grouped_circuits')->default(1);
            $table->unsignedSmallInteger('parallel_conductors')->default(1);
            $table->decimal('max_voltage_drop', 5, 2);
            $table->decimal('protective_device', 8, 1)->nullable();
            $table->decimal('short_circuit_ka', 8, 2)->nullable();
            $table->decimal('short_circuit_time', 6, 3)->nullable();
            $table->text('notes')->nullable();
            $table->json('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cable_calculations');
    }
};
