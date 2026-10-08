<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('street', 150)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['teams', 'vehicles', 'assets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $this->contactColumns($table);
            $table->timestamps();
        });

        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $this->contactColumns($table);
            $table->string('store_url')->nullable();
            $table->string('price_provider', 20)->nullable();
            $table->string('api_username')->nullable();
            $table->text('api_password')->nullable();
            $table->string('api_customer_number', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('distributor_manufacturer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->unique(['distributor_id', 'manufacturer_id']);
        });

        Schema::create('distributor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('job_title', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('stock_categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['parent_id', 'name']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('abbreviation', 10)->unique();
            $table->boolean('allows_decimals')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('stock_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('manufacturer_reference', 100)->nullable()->index();
            $table->string('distributor_reference', 100)->nullable()->index();
            $table->string('ean', 20)->nullable()->index();
            $table->decimal('market_price', 12, 4)->nullable();
            $table->timestamp('price_updated_at')->nullable();
            $table->string('price_source', 30)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('min_quantity', 12, 3)->nullable();
            $table->timestamps();
            $table->unique(['stock_item_id', 'location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('change', 12, 3);
            $table->decimal('quantity_after', 12, 3);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('stock_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 4);
            $table->string('source', 30);
            $table->timestamp('fetched_at');
        });
    }

    public function down(): void
    {
        foreach (['stock_item_prices', 'stock_movements', 'stock_levels', 'stock_items', 'units', 'stock_categories', 'distributor_contacts', 'distributor_manufacturer', 'distributors', 'manufacturers'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['teams', 'vehicles', 'assets'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
        }

        Schema::dropIfExists('locations');
    }

    /**
     * Website, email, phone, address, logo and notes, shared by manufacturers and distributors.
     */
    protected function contactColumns(Blueprint $table): void
    {
        $table->string('website')->nullable();
        $table->string('email', 150)->nullable();
        $table->string('phone', 30)->nullable();
        $table->string('street', 150)->nullable();
        $table->string('postal_code', 10)->nullable();
        $table->string('city', 100)->nullable();
        $table->string('country', 100)->nullable();
        $table->string('logo')->nullable();
        $table->text('notes')->nullable();
    }
};
