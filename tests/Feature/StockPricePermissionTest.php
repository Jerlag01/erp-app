<?php

namespace Tests\Feature;

use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\LevelsRelationManager;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockPricePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function employee(array $permissions): User
    {
        $location = Location::factory()->create();

        return User::factory()->withPermissions($permissions)->inTeam(Team::factory()->create(['location_id' => $location->id]))->create();
    }

    #[Test]
    public function prices_are_hidden_without_the_price_permission(): void
    {
        $employee = $this->employee(['stock_items' => ['view'], 'stock' => ['adjust']]);
        $item = StockItem::factory()->create(['market_price' => 13.37]);
        Filament::setCurrentPanel('app');
        $this->actingAs($employee);

        $this->assertFalse($employee->canSeePrices());
        $this->get("/stock-items/{$item->id}")->assertOk()->assertSee($item->name)->assertDontSee('13,37');

        Livewire::test(ListStockItems::class)
            ->assertCanSeeTableRecords([$item])
            ->assertTableColumnHidden('market_price')
            ->assertDontSee('13,37');

        Livewire::test(LevelsRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertTableColumnHidden('value');
    }

    #[Test]
    public function prices_are_shown_with_the_price_permission_and_to_administrators(): void
    {
        $item = StockItem::factory()->create(['market_price' => 13.37]);

        foreach ([$this->employee(['stock_items' => ['view'], 'stock_prices' => ['view']]), User::factory()->admin()->create()] as $user) {
            $this->assertTrue($user->canSeePrices());
            $this->actingAs($user)->get("/stock-items/{$item->id}")->assertOk()->assertSee('13,37');
        }
    }

    #[Test]
    public function the_list_cannot_be_sorted_on_the_hidden_price(): void
    {
        $cheap = StockItem::factory()->create(['name' => 'A cheap', 'market_price' => 1]);
        $dear = StockItem::factory()->create(['name' => 'B dear', 'market_price' => 999]);
        Filament::setCurrentPanel('app');

        Livewire::actingAs($this->employee(['stock_items' => ['view']]))
            ->test(ListStockItems::class)
            ->sortTable('market_price', 'desc')
            ->assertCanSeeTableRecords([$cheap, $dear], inOrder: true);
    }

    #[Test]
    public function editing_without_the_price_permission_keeps_the_price(): void
    {
        $item = StockItem::factory()->create(['market_price' => 13.37]);
        Filament::setCurrentPanel('app');

        Livewire::actingAs($this->employee(['stock_items' => ['view', 'update']]))
            ->test(EditStockItem::class, ['record' => $item->getRouteKey()])
            ->assertFormFieldHidden('market_price')
            ->fillForm(['name' => 'Renamed', 'market_price' => 0])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame('Renamed', $item->name);
        $this->assertSame('13.3700', $item->market_price);
    }

    #[Test]
    public function the_price_permission_can_be_given_per_role(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/roles/create')->assertOk()->assertSee('stock_prices', false);
    }
}
