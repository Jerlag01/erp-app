<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\ImportImageFromUrl;
use App\Actions\RefreshStockItemFromDistributor;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\LevelsRelationManager;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use App\Services\Distributors\DistributorException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_item_gets_a_stock_row_at_every_location_and_vice_versa(): void
    {
        $luik = Location::factory()->create();
        $namen = Location::factory()->create();

        $item = StockItem::factory()->create();
        $this->assertEqualsCanonicalizing([$luik->id, $namen->id], $item->levels()->pluck('location_id')->all());
        $this->assertSame(0.0, (float) $item->levels()->sum('quantity'));

        $gent = Location::factory()->create();
        $this->assertTrue(StockLevel::where('stock_item_id', $item->id)->where('location_id', $gent->id)->exists());
    }

    #[Test]
    public function stock_is_raised_and_lowered_with_a_logged_movement_but_never_below_zero(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create();
        $level = StockItem::factory()->create()->levels()->sole();

        app(AdjustStock::class)->handle($level, 10, $user, 'Levering');
        app(AdjustStock::class)->handle($level, -3, $user, 'Werf Luik');

        $this->assertSame('7.000', $level->fresh()->quantity);
        $this->assertSame(['10.000', '-3.000'], $level->movements()->reorder('id')->pluck('change')->all());
        $this->assertSame($user->id, $level->movements()->first()->user_id);

        $this->expectException(ValidationException::class);
        app(AdjustStock::class)->handle($level, -8, $user);
    }

    #[Test]
    public function pieces_are_whole_numbers_and_meters_may_have_decimals(): void
    {
        Location::factory()->create();
        $user = User::factory()->create();
        $pieces = StockItem::factory()->create()->levels()->sole();
        $cable = StockItem::factory()->inMeters()->create()->levels()->sole();

        app(AdjustStock::class)->handle($cable, 12.5, $user);
        $this->assertSame('12.500', $cable->fresh()->quantity);

        $this->expectException(ValidationException::class);
        app(AdjustStock::class)->handle($pieces, 1.5, $user);
    }

    #[Test]
    public function a_stocktake_logs_only_the_difference(): void
    {
        Location::factory()->create();
        $user = User::factory()->create();
        $level = StockItem::factory()->create()->levels()->sole();
        app(AdjustStock::class)->handle($level, 20, $user);

        app(AdjustStock::class)->setTo($level, 17, $user);

        $this->assertSame('17.000', $level->fresh()->quantity);
        $this->assertSame('-3.000', $level->movements()->first()->change);
        $this->assertNull(app(AdjustStock::class)->setTo($level->fresh(), 17, $user));
    }

    #[Test]
    public function employees_only_see_and_adjust_the_stock_of_their_teams_location(): void
    {
        $luik = Location::factory()->create(['name' => 'Depot Luik']);
        $namen = Location::factory()->create(['name' => 'Depot Namen']);
        $team = Team::factory()->create(['location_id' => $luik->id]);
        $employee = User::factory()->withPermissions(['stock_items' => ['view'], 'stock' => ['adjust']])->inTeam($team)->create();
        $item = StockItem::factory()->create();
        $mine = $item->levels()->where('location_id', $luik->id)->sole();
        $theirs = $item->levels()->where('location_id', $namen->id)->sole();

        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(LevelsRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->callTableAction('add', $mine, ['quantity' => 5, 'note' => 'Levering'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('remove', $mine, ['quantity' => 2])
            ->assertHasNoTableActionErrors();

        $this->assertSame('3.000', $mine->fresh()->quantity);
    }

    #[Test]
    public function without_the_adjust_permission_the_buttons_are_hidden(): void
    {
        $location = Location::factory()->create();
        $viewer = User::factory()->withPermissions(['stock_items' => ['view']])->inTeam(Team::factory()->create(['location_id' => $location->id]))->create();
        $item = StockItem::factory()->create();
        $this->actingAs($viewer);
        Filament::setCurrentPanel('app');

        Livewire::test(LevelsRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertTableActionHidden('add', $item->levels()->sole())
            ->assertTableActionHidden('remove', $item->levels()->sole());
    }

    #[Test]
    public function the_qr_code_opens_the_item_page_after_login(): void
    {
        $item = StockItem::factory()->create();

        $this->get("/q/{$item->id}")->assertRedirect("/stock-items/{$item->id}");
        $this->get("/stock-items/{$item->id}")->assertRedirect('/login');

        $user = User::factory()->withPermissions(['stock_items' => ['view']])->inTeam()->create();
        $this->actingAs($user)->get("/stock-items/{$item->id}")->assertOk()->assertSee($item->name)->assertSee('<svg', false);
    }

    #[Test]
    public function labels_print_a_qr_code_with_the_scan_link(): void
    {
        $items = StockItem::factory()->count(2)->create();
        $user = User::factory()->withPermissions(['stock_items' => ['view']])->inTeam()->create();

        $this->get(route('stock.labels', ['items' => $items->modelKeys()]))->assertRedirect('/login');
        $this->actingAs($user)->get(route('stock.labels', ['items' => $items->modelKeys()]))
            ->assertOk()
            ->assertSee($items[0]->name)
            ->assertSee($items[1]->manufacturer_reference)
            ->assertSee('<svg', false);
        $this->actingAs(User::factory()->inTeam()->create())->get(route('stock.labels', ['items' => [$items[0]->id]]))->assertForbidden();
    }

    #[Test]
    public function filtering_on_a_category_includes_its_subcategories(): void
    {
        $cables = StockCategory::factory()->create(['name' => 'Kabels']);
        $xvb = StockCategory::factory()->create(['name' => 'XVB', 'parent_id' => $cables->id]);
        $fuses = StockCategory::factory()->create(['name' => 'Zekeringen']);
        $xvbCable = StockItem::factory()->create(['stock_category_id' => $xvb->id]);
        $fuse = StockItem::factory()->create(['stock_category_id' => $fuses->id]);

        $this->assertSame([$xvbCable->id], StockItem::inCategory($cables)->pluck('id')->all());

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ListStockItems::class)
            ->filterTable('category', $cables->id)
            ->assertCanSeeTableRecords([$xvbCable])
            ->assertCanNotSeeTableRecords([$fuse])
            ->resetTableFilters()
            ->filterTable('subcategory', $fuses->id)
            ->assertCanSeeTableRecords([$fuse])
            ->assertCanNotSeeTableRecords([$xvbCable]);

        $this->assertSame('Kabels › XVB', $xvb->fullName());
        $this->assertFalse(auth()->user()->can('delete', $cables));
        $this->assertFalse(auth()->user()->can('delete', $fuses));
        $this->assertTrue(auth()->user()->can('delete', StockCategory::factory()->create()));
    }

    #[Test]
    public function units_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $used = StockItem::factory()->create()->unit;

        $this->assertFalse($admin->can('delete', $used));
        $this->assertTrue($admin->can('delete', Unit::factory()->create()));
    }

    #[Test]
    public function a_distributor_without_working_api_explains_what_is_missing(): void
    {
        $distributor = Distributor::factory()->create(['name' => 'Cebeo', 'price_provider' => 'cebeo']);
        $item = StockItem::factory()->for($distributor)->create();

        try {
            app(RefreshStockItemFromDistributor::class)->handle($item);
            $this->fail('Expected the connection to be reported as not set up.');
        } catch (DistributorException $exception) {
            $this->assertStringContainsString('Cebeo', $exception->getMessage());
        }

        $this->assertFalse($distributor->client()->isConfigured());
        $this->assertNull(Distributor::factory()->create()->client());
    }

    #[Test]
    public function api_passwords_are_stored_encrypted(): void
    {
        $distributor = Distributor::factory()->create(['price_provider' => 'rexel', 'api_password' => 'geheim']);

        $this->assertSame('geheim', $distributor->fresh()->api_password);
        $this->assertNotSame('geheim', DB::table('distributors')->where('id', $distributor->id)->value('api_password'));
    }

    #[Test]
    public function prices_keep_a_history(): void
    {
        $item = StockItem::factory()->create();

        $item->recordPrice(12.40, 'cebeo');
        $item->recordPrice(12.95, 'cebeo');

        $this->assertSame('12.9500', $item->fresh()->market_price);
        $this->assertCount(2, $item->prices);
    }

    #[Test]
    public function images_can_be_imported_from_a_public_web_address_only(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/a9f.png' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
            'example.com/page' => Http::response('<html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $path = app(ImportImageFromUrl::class)->handle('https://example.com/a9f.png', 'stock-items');
        Storage::disk('public')->assertExists($path);

        foreach (['http://127.0.0.1/admin', 'http://localhost/a.png', 'ftp://example.com/a.png', 'https://example.com/page', 'https://does-not-exist.invalid/a.png'] as $url) {
            try {
                app(ImportImageFromUrl::class)->handle($url, 'stock-items');
                $this->fail("{$url} should have been refused.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function the_stock_module_can_be_switched_off(): void
    {
        $admin = User::factory()->admin()->create();
        $item = StockItem::factory()->create();

        $this->setModules(['stock' => false]);

        $this->actingAs($admin)->get('/admin/stock-items')->assertForbidden();
        $this->actingAs($admin)->get('/admin/manufacturers')->assertForbidden();
        $this->get("/q/{$item->id}")->assertNotFound();
    }

    #[Test]
    public function every_stock_page_renders_for_an_administrator(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();
        $item = StockItem::factory()->create(['stock_category_id' => StockCategory::first()->id, 'distributor_id' => Distributor::first()->id]);
        app(AdjustStock::class)->handle($item->levels()->first(), 4, $admin);
        $location = Location::first();

        foreach (['/admin', ''] as $prefix) {
            foreach (['/stock-items', '/stock-items/create', "/stock-items/{$item->id}", "/stock-items/{$item->id}/edit", '/manufacturers', '/manufacturers/create',
                '/distributors', '/distributors/create', '/distributors/'.Distributor::first()->id.'/edit', '/distributor-contacts', '/stock-categories', '/units',
                '/locations', '/locations/create', "/locations/{$location->id}", "/locations/{$location->id}/edit"] as $url) {
                $this->actingAs($admin)->get($prefix.$url)->assertOk();
            }
        }
    }
}
