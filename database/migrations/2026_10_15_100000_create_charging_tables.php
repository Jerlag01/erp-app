<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charging stations (the Excel "Overzicht Laadpalen", 608 projects) and 4G routers per COW site.
 * Passwords, recovery codes and the router's VPN file are secrets: stored encrypted (the VPN file on
 * the private disk) and only shown with the "charging.secrets" permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('fully_executed')->default(false);
            $table->string('ace_number', 30)->nullable()->unique();
            // Encrypted.
            $table->text('password')->nullable();
            $table->text('recovery_code')->nullable();
            $table->string('sim_ean', 30)->nullable();
            $table->string('sim_ssn', 30)->nullable();
            // mobile, modem_wired or wired.
            $table->string('connectivity', 20)->nullable();
            $table->string('license_key', 100)->nullable();
            // standalone or cluster.
            $table->string('type', 20)->default('standalone');
            $table->boolean('online')->default(true);
            $table->string('backoffice', 100)->nullable();
            $table->unsignedSmallInteger('installation_year')->nullable();
            $table->string('brand', 100)->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->unsignedSmallInteger('cluster_number')->nullable();
            // duo or single.
            $table->string('system', 10)->nullable();
            $table->boolean('in_service')->default(false);
            $table->string('commissioned_by', 100)->nullable();
            $table->date('onsite_warranty_end')->nullable();
            $table->boolean('plans_updated')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_site_id', 'cluster_number']);
        });

        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained()->restrictOnDelete();
            $table->string('type', 100);
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('sim_ean', 30)->nullable();
            $table->string('sim_ssn', 30)->nullable();
            $table->string('username', 100)->nullable();
            // Encrypted.
            $table->text('password')->nullable();
            // Private disk; downloaded through a controller that checks the permission.
            $table->string('vpn_file')->nullable();
            $table->string('vpn_file_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
        Schema::dropIfExists('charging_stations');
    }
};
