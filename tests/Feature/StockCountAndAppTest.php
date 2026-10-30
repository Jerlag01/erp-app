<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\LevelsRelationManager;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockCount;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockCountAndAppTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    #[Test]
    public function the_name_is_given_in_one_language_or_more_and_shown_in_the_users_language(): void
    {
        $this->admin();
        $unit = Unit::factory()->create();

        Livewire::test(CreateStockItem::class)
            ->fillForm(['unit_id' => $unit->id])
            ->call('create')
            ->assertHasFormErrors(['name_nl', 'name_fr', 'name_en']);

        Livewire::test(CreateStockItem::class)
            ->fillForm(['name_fr' => 'Disjoncteur 16A', 'unit_id' => $unit->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockItem::query()->sole();
        $this->assertSame('Disjoncteur 16A', $item->name);
        $this->assertSame('Disjoncteur 16A', $item->localizedName('nl'), 'Without a Dutch name the one filled in is shown.');

        $item->update(['name_nl' => 'Automaat 16A', 'name_en' => 'Circuit breaker 16A']);
        $this->assertSame('Automaat 16A', $item->fresh()->name);
        $this->assertSame('Circuit breaker 16A', $item->fresh()->localizedName('en'));
        $this->assertSame('Disjoncteur 16A', $item->fresh()->localizedName('fr'));

        // Searching finds every language.
        Livewire::test(ListStockItems::class)->searchTable('Circuit breaker')->assertCanSeeTableRecords([$item]);
    }

    #[Test]
    public function a_notification_in_the_platform_is_sent_when_stock_drops_to_its_minimum(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->withPermissions(['stock_items' => ['view', 'update']])->inTeam()->create();
        $employee = User::factory()->withPermissions(['stock_items' => ['view'], 'stock' => ['adjust']])->inTeam()->create();
        Location::factory()->create(['name' => 'Depot Luik']);
        $item = StockItem::factory()->create(['name_nl' => 'Kabel XVB', 'low_stock_threshold' => 5]);
        $level = $item->levels()->sole();

        app(AdjustStock::class)->handle($level, 10, $employee);
        $this->assertSame(0, $admin->notifications()->count());

        // Down to the minimum: the administrator and the stock manager are told, the employee is not.
        app(AdjustStock::class)->handle($level, -5, $employee);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $employee->notifications()->count());
        $this->assertStringContainsString('Kabel XVB', $admin->notifications()->first()->data['title']);
        $this->assertStringContainsString('Depot Luik', $admin->notifications()->first()->data['title']);

        // Lower still: no new notification until it was above the minimum again.
        app(AdjustStock::class)->handle($level, -1, $employee);
        $this->assertSame(1, $admin->notifications()->count());
    }

    #[Test]
    public function during_the_yearly_count_stock_can_only_be_counted_on_the_platform_and_in_the_app(): void
    {
        $admin = $this->admin();
        Location::factory()->create();
        $item = StockItem::factory()->create();
        $level = $item->levels()->sole();
        app(AdjustStock::class)->handle($level, 8, $admin);

        Livewire::test(ListStockItems::class)->assertActionVisible('startYearlyCount')->callAction('startYearlyCount');
        $this->assertTrue(app(StockCount::class)->active());

        // Add and take are gone on the platform; counting stays and marks the row.
        Livewire::test(LevelsRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertTableActionHidden('add', $level)
            ->assertTableActionHidden('remove', $level)
            ->assertTableActionVisible('count', $level)
            ->callTableAction('count', $level, ['quantity' => 6, 'note' => null])
            ->assertHasNoTableActionErrors();
        $this->assertSame('6.000', $level->fresh()->quantity);
        $this->assertNotNull($level->fresh()->counted_at);
        $this->assertSame(['counted' => 1, 'total' => 1], app(StockCount::class)->progress());

        try {
            app(AdjustStock::class)->handle($level->fresh(), 1, $admin);
            $this->fail('Adding during the yearly count is blocked.');
        } catch (ValidationException) {
        }

        // The app: the same.
        $employee = User::factory()->withPermissions(['stock_items' => ['view'], 'stock' => ['adjust']])->inTeam()->create();
        $auth = $this->mobileAuth($employee);
        $this->getJson("/api/v1/stock/items/{$item->id}", $auth)->assertOk()->assertJsonPath('data.yearly_count', true);
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'take', 'quantity' => 1], $auth)->assertUnprocessable();
        $this->postJson("/api/v1/stock/levels/{$level->id}/count", ['quantity' => 7], $auth)->assertOk()->assertJsonPath('data.levels.0.quantity', 7);

        // Ending it: adding and taking again.
        $this->actingAs($admin);
        Livewire::test(ListStockItems::class)->callAction('stopYearlyCount');
        $this->assertFalse(app(StockCount::class)->active());
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'take', 'quantity' => 2], $auth)->assertOk()->assertJsonPath('data.levels.0.quantity', 5);

        // Only administrators start it.
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');
        Livewire::test(ListStockItems::class)->assertActionHidden('startYearlyCount');
    }

    #[Test]
    public function a_scanned_label_opens_the_item_with_the_stock_at_every_location(): void
    {
        $own = Location::factory()->create(['name' => 'A Depot Luik']);
        $other = Location::factory()->create(['name' => 'B Depot Gent']);
        $team = Team::factory()->create(['location_id' => $own->id]);
        $employee = User::factory()->withPermissions(['stock_items' => ['view'], 'stock' => ['adjust']])->inTeam($team)->create(['locale' => 'en']);
        $viewer = User::factory()->withPermissions(['stock_items' => ['view']])->inTeam($team)->create();
        $item = StockItem::factory()->create(['name_nl' => 'Automaat 16A', 'name_en' => 'Circuit breaker 16A', 'ean' => '5412345678901', 'manufacturer_reference' => 'A9F74216', 'notes' => 'Bovenste rek']);
        $item->suppliers()->create(['distributor_id' => Distributor::factory()->create(['name' => 'Rexel'])->id, 'reference' => 'RX-123', 'is_preferred' => true]);
        $auth = $this->mobileAuth($employee);

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.stock', true)->assertJsonPath('data.features.stock_adjust', true);
        $this->getJson('/api/v1/me', $this->mobileAuth($viewer))->assertJsonPath('data.features.stock_adjust', false);
        $this->getJson('/api/v1/me', $this->mobileAuth(User::factory()->withPermissions([])->inTeam()->create()))->assertJsonPath('data.features.stock', false);

        // The QR label, the EAN barcode or a supplier's article number.
        $this->getJson('/api/v1/stock/lookup?code='.urlencode(route('stock.qr', $item)), $auth)->assertOk()->assertJsonPath('data.id', $item->id);
        $this->getJson('/api/v1/stock/lookup?code=5412345678901', $auth)->assertOk()->assertJsonPath('data.id', $item->id);
        $this->getJson('/api/v1/stock/lookup?code=RX-123', $auth)->assertOk()->assertJsonPath('data.id', $item->id);
        $this->getJson('/api/v1/stock/lookup?code=nothing', $auth)->assertNotFound();
        $this->getJson('/api/v1/stock/search?search=breaker', $auth)->assertOk()->assertJsonPath('data.0.id', $item->id);

        // Every location, also the one the employee does not belong to.
        $this->getJson("/api/v1/stock/items/{$item->id}", $auth)->assertOk()
            ->assertJsonPath('data.name', 'Circuit breaker 16A')
            ->assertJsonPath('data.manufacturer_reference', 'A9F74216')
            ->assertJsonPath('data.notes', 'Bovenste rek')
            ->assertJsonPath('data.suppliers.0.name', 'Rexel')
            ->assertJsonPath('data.suppliers.0.reference', 'RX-123')
            ->assertJsonPath('data.levels.0.location', 'A Depot Luik')
            ->assertJsonPath('data.levels.1.location', 'B Depot Gent')
            ->assertJsonPath('data.can_adjust', true);

        $level = $item->levels()->where('location_id', $other->id)->sole();
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'add', 'quantity' => 4], $auth)->assertOk()->assertJsonPath('data.levels.1.quantity', 4);
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'take', 'quantity' => 9], $auth)->assertUnprocessable();
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'take', 'quantity' => 1], $auth)->assertOk()->assertJsonPath('data.levels.1.quantity', 3);
        $this->assertSame($employee->id, $level->movements()->latest('id')->first()->user_id);

        // Without "stock.adjust": only looking.
        $this->postJson("/api/v1/stock/levels/{$level->id}/adjust", ['direction' => 'add', 'quantity' => 1], $this->mobileAuth($viewer))->assertForbidden();
    }
}
