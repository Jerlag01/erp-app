<?php

namespace Tests\Feature;

use App\Filament\Resources\ItModels\Pages\ManageItModels;
use App\Models\Distributor;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItLicense;
use App\Models\ItManufacturer;
use App\Models\ItModel;
use App\Models\ItSupplier;
use App\Models\Manufacturer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItSeparateListsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_have_no_link_to_the_stock_manufacturers_or_distributors(): void
    {
        foreach (['it_models', 'it_licenses', 'it_items'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'manufacturer_id'), $table);
            $this->assertTrue(Schema::hasColumn($table, 'it_manufacturer_id'), $table);
        }

        foreach (['it_assets', 'it_maintenances', 'it_licenses', 'it_items'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'supplier_id'), $table);
            $this->assertTrue(Schema::hasColumn($table, 'it_supplier_id'), $table);
        }

        $dell = ItManufacturer::factory()->create(['name' => 'Dell']);
        $supplier = ItSupplier::factory()->create(['name' => 'Bechtle']);
        $model = ItModel::factory()->for($dell, 'manufacturer')->create(['name' => 'Latitude 5440']);
        $asset = ItAsset::factory()->for($model, 'model')->for($supplier, 'supplier')->create();

        $this->assertSame('Dell Latitude 5440', $asset->model->fullName());
        $this->assertTrue($asset->supplier->is($supplier));
    }

    #[Test]
    public function the_it_forms_only_offer_it_manufacturers(): void
    {
        Manufacturer::factory()->create(['name' => 'Schneider Electric']);
        Distributor::factory()->create(['name' => 'Cebeo']);
        ItManufacturer::factory()->create(['name' => 'Lenovo']);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageItModels::class)
            ->mountAction('create')
            ->assertSee('Lenovo')
            ->assertDontSee('Schneider Electric');

        $this->get('/admin/it-assets/create')->assertOk()->assertDontSee('Cebeo');
    }

    #[Test]
    public function the_it_lists_work_with_the_stock_module_switched_off(): void
    {
        $this->setModules(['stock' => false]);
        $admin = User::factory()->admin()->create();
        $manufacturer = ItManufacturer::factory()->create();
        $supplier = ItSupplier::factory()->create();

        foreach (['/admin/it-manufacturers', '/admin/it-manufacturers/create', "/admin/it-manufacturers/{$manufacturer->id}/edit",
            '/admin/it-suppliers', '/admin/it-suppliers/create', "/admin/it-suppliers/{$supplier->id}/edit"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($admin)->get('/admin/manufacturers')->assertForbidden();
    }

    #[Test]
    public function manufacturers_and_suppliers_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $usedManufacturer = ItManufacturer::factory()->create();
        ItLicense::factory()->for($usedManufacturer, 'manufacturer')->create();
        $usedSupplier = ItSupplier::factory()->create();
        ItItem::factory()->for($usedSupplier, 'supplier')->create();

        $this->assertFalse($admin->can('delete', $usedManufacturer));
        $this->assertFalse($admin->can('delete', $usedSupplier));
        $this->assertTrue($admin->can('delete', ItManufacturer::factory()->create()));
        $this->assertTrue($admin->can('delete', ItSupplier::factory()->create()));
    }
}
