<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Widgets\LowStock;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LowStockThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function levelWith(float $quantity, ?float $threshold, ?float $locationMinimum = null): StockLevel
    {
        $location = Location::query()->first() ?? Location::factory()->create();
        $level = StockItem::factory()->create(['low_stock_threshold' => $threshold])->levels()->where('location_id', $location->id)->sole();
        $level->update(['quantity' => $quantity, 'min_quantity' => $locationMinimum]);

        return $level->fresh();
    }

    #[Test]
    public function an_item_is_flagged_on_reaching_its_threshold_before_it_runs_out(): void
    {
        $this->assertFalse($this->levelWith(6, 5)->isLow());
        $this->assertTrue($this->levelWith(5, 5)->isLow());
        $this->assertTrue($this->levelWith(1, 5)->isLow());
        $this->assertFalse($this->levelWith(0, null)->isLow(), 'Without a threshold nothing is flagged.');
    }

    #[Test]
    public function the_threshold_applies_at_every_location_and_a_location_can_override_it(): void
    {
        $first = Location::factory()->create();
        $second = Location::factory()->create();
        $item = StockItem::factory()->create(['low_stock_threshold' => 10]);
        $item->levels()->update(['quantity' => 8]);
        $item->levels()->where('location_id', $second->id)->update(['min_quantity' => 4]);

        $this->assertEqualsCanonicalizing([$first->id], StockLevel::query()->low()->pluck('location_id')->all());

        $atSecond = $item->levels()->where('location_id', $second->id)->sole();
        $this->assertSame('4.000', $atSecond->threshold());
        $this->assertFalse($atSecond->isLow());
    }

    #[Test]
    public function the_query_and_the_model_agree(): void
    {
        foreach ([[6, 5, null], [5, 5, null], [3, 5, 2], [2, 5, 2], [0, null, null], [0, null, 1]] as [$quantity, $threshold, $minimum]) {
            $level = $this->levelWith($quantity, $threshold, $minimum);

            $this->assertSame($level->isLow(), StockLevel::query()->low()->whereKey($level->id)->exists(), "q={$quantity} t={$threshold} m={$minimum}");
        }
    }

    #[Test]
    public function taking_stock_down_to_the_threshold_puts_it_on_the_low_stock_list(): void
    {
        Location::factory()->create();
        $admin = User::factory()->admin()->create();
        $item = StockItem::factory()->create(['low_stock_threshold' => 3]);
        $level = $item->levels()->sole();
        app(AdjustStock::class)->handle($level, 10, $admin);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(LowStock::class)->assertCanNotSeeTableRecords([$level]);

        app(AdjustStock::class)->handle($level->fresh(), -7, $admin);
        Livewire::test(LowStock::class)->assertCanSeeTableRecords([$level]);
    }

    #[Test]
    public function the_threshold_is_entered_on_the_item(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        $unit = Unit::factory()->create(['allows_decimals' => true, 'abbreviation' => 'm']);

        Livewire::test(CreateStockItem::class)
            ->fillForm(['name' => 'XVB 3G2,5', 'unit_id' => $unit->id, 'low_stock_threshold' => 50])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('50.000', StockItem::sole()->low_stock_threshold);
    }
}
