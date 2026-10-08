<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The IT module gets its own manufacturers and suppliers, fully separate from the stock module.
 * Existing links are copied over by name before the old columns are dropped.
 */
return new class extends Migration
{
    /** @var list<string> */
    protected array $manufacturerTables = ['it_models', 'it_licenses', 'it_items'];

    /** @var list<string> */
    protected array $supplierTables = ['it_assets', 'it_maintenances', 'it_licenses', 'it_items'];

    public function up(): void
    {
        Schema::create('it_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $this->companyColumns($table);
            $table->string('support_url')->nullable();
            $table->string('support_email', 150)->nullable();
            $table->string('support_phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('it_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $this->companyColumns($table);
            $table->string('store_url')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->timestamps();
        });

        foreach ($this->manufacturerTables as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->foreignId('it_manufacturer_id')->nullable()->after('id')->constrained()->nullOnDelete());
            $this->copy($table, 'manufacturer_id', 'manufacturers', 'it_manufacturers', 'it_manufacturer_id');
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('manufacturer_id'));
        }

        foreach ($this->supplierTables as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->foreignId('it_supplier_id')->nullable()->after('id')->constrained()->nullOnDelete());
            $this->copy($table, 'supplier_id', 'distributors', 'it_suppliers', 'it_supplier_id');
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_id'));
        }
    }

    public function down(): void
    {
        foreach ($this->supplierTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('it_supplier_id');
                $table->foreignId('supplier_id')->nullable()->constrained('distributors')->nullOnDelete();
            });
        }

        foreach ($this->manufacturerTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('it_manufacturer_id');
                $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::dropIfExists('it_suppliers');
        Schema::dropIfExists('it_manufacturers');
    }

    protected function companyColumns(Blueprint $table): void
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

    /**
     * Re-creates each linked stock company in the IT list (by name) and points the row at it.
     */
    protected function copy(string $table, string $oldColumn, string $source, string $target, string $newColumn): void
    {
        foreach (DB::table($table)->whereNotNull($oldColumn)->distinct()->pluck($oldColumn) as $oldId) {
            $company = DB::table($source)->find($oldId);

            if ($company === null) {
                continue;
            }

            $newId = DB::table($target)->where('name', $company->name)->value('id') ?? DB::table($target)->insertGetId([
                'name' => $company->name,
                'website' => $company->website,
                'email' => $company->email,
                'phone' => $company->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table($table)->where($oldColumn, $oldId)->update([$newColumn => $newId]);
        }
    }
};
