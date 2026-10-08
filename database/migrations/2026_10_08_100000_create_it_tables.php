<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        Schema::create('it_status_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('type', 20);
            $table->string('color', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('it_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('it_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_number', 100)->nullable();
            $table->unsignedSmallInteger('eol_months')->nullable();
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 40)->unique();
            $table->string('name', 150)->nullable();
            $table->foreignId('it_model_id')->constrained()->restrictOnDelete();
            $table->string('serial', 100)->nullable()->index();
            $table->foreignId('it_status_label_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->date('expected_checkin')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('distributors')->nullOnDelete();
            $table->string('order_number', 50)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->date('last_audit_date')->nullable();
            $table->date('next_audit_date')->nullable()->index();
            $table->string('hostname', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->json('specs')->nullable();
            $table->boolean('requestable')->default(false);
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('it_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->foreignId('supplier_id')->nullable()->constrained('distributors')->nullOnDelete();
            $table->date('start_date');
            $table->date('completion_date')->nullable();
            $table->boolean('is_warranty')->default(false);
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('it_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('it_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('product_key')->nullable();
            $table->unsignedInteger('seats')->default(1);
            $table->string('licensed_to_name', 150)->nullable();
            $table->string('licensed_to_email', 150)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('distributors')->nullOnDelete();
            $table->string('order_number', 50)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->date('expiration_date')->nullable()->index();
            $table->boolean('reassignable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('it_license_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('it_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('it_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();
            $table->string('name', 150);
            $table->foreignId('it_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_number', 100)->nullable();
            $table->string('serial', 100)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('min_quantity')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('distributors')->nullOnDelete();
            $table->string('order_number', 50)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('it_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('it_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('it_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->string('action', 20);
            $table->nullableMorphs('target');
            $table->unsignedInteger('quantity')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['it_logs', 'it_item_assignments', 'it_items', 'it_license_seats', 'it_licenses', 'it_maintenances', 'it_assets', 'it_models', 'it_status_labels', 'it_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
