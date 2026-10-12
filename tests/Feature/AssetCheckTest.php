<?php

namespace Tests\Feature;

use App\Enums\AssetCheckResult;
use App\Enums\AssetStatus;
use App\Filament\Resources\AssetChecks\Pages\ManageAssetChecks;
use App\Filament\Resources\AssetChecks\Pages\ViewAssetCheck;
use App\Filament\Resources\AssetChecks\RelationManagers\ItemsRelationManager;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCheck;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetCheckTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create(['name' => 'Ploeg Noord']);
        $this->member = User::factory()->withPermissions([])->inTeam($this->team)->create();
    }

    protected function publish(User $by): AssetCheck
    {
        $this->actingAs($by);

        Livewire::test(ManageAssetChecks::class)
            ->callAction('publish', ['teams' => [$this->team->id], 'year' => 2026])
            ->assertHasNoActionErrors();

        return AssetCheck::query()->sole();
    }

    #[Test]
    public function the_list_is_only_visible_to_the_team_while_it_is_open(): void
    {
        $this->actingAs($this->member)->get('/asset-checks')->assertForbidden();

        $check = $this->publish(User::factory()->withPermissions(['assets' => ['control']])->inTeam()->create());
        $this->assertTrue($check->isOpen());
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($this->member)->get('/asset-checks')->assertOk()->assertSee('Ploeg Noord');
        $this->get("/asset-checks/{$check->id}")->assertOk();

        // Someone from another team does not see it.
        $this->actingAs(User::factory()->withPermissions([])->inTeam()->create())->get("/asset-checks/{$check->id}")->assertNotFound();

        $check->update(['closed_at' => now()]);
        $this->actingAs($this->member)->get('/asset-checks')->assertForbidden();
        $this->get("/asset-checks/{$check->id}")->assertNotFound();
    }

    #[Test]
    public function only_admins_and_the_control_right_publish(): void
    {
        $check = $this->publish(User::factory()->admin()->create());

        $this->actingAs($this->member);
        Livewire::test(ManageAssetChecks::class)->assertActionHidden('publish');
        Livewire::test(ViewAssetCheck::class, ['record' => $check->getRouteKey()])->assertActionHidden('close');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewAssetCheck::class, ['record' => $check->getRouteKey()])->callAction('close');
        $this->assertFalse($check->fresh()->isOpen());

        Livewire::test(ViewAssetCheck::class, ['record' => $check->getRouteKey()])->callAction('reopen');
        $this->assertTrue($check->fresh()->isOpen());
    }

    #[Test]
    public function the_team_marks_its_equipment_and_ok_updates_the_control_date(): void
    {
        $category = AssetCategory::factory()->create(['inspection_interval_months' => 6]);
        $drill = Asset::factory()->for($category, 'category')->create(['team_id' => $this->team->id, 'inspection_date' => '2025-01-10']);
        $ladder = Asset::factory()->create(['vehicle_id' => Vehicle::factory()->create(['team_id' => $this->team->id])->id, 'inspection_date' => '2025-01-10']);
        Asset::factory()->create(['team_id' => $this->team->id, 'status' => AssetStatus::Retired]);
        Asset::factory()->create(['team_id' => Team::factory()->create()->id]);

        $check = $this->publish(User::factory()->admin()->create());
        $this->assertEqualsCanonicalizing([$drill->id, $ladder->id], $check->items()->pluck('asset_id')->all());

        $this->actingAs($this->member);
        $manager = Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $check, 'pageClass' => ViewAssetCheck::class]);
        $drillItem = $check->items()->where('asset_id', $drill->id)->sole();
        $ladderItem = $check->items()->where('asset_id', $ladder->id)->sole();

        $manager->call('updateTableColumnState', 'result', (string) $drillItem->getKey(), 'ok');
        $manager->call('updateTableColumnState', 'result', (string) $ladderItem->getKey(), 'not_ok');
        $manager->call('updateTableColumnState', 'remark', (string) $ladderItem->getKey(), 'Sport gebroken');

        $drillItem->refresh();
        $this->assertSame(AssetCheckResult::Ok, $drillItem->result);
        $this->assertSame($this->member->id, $drillItem->checked_by);
        $this->assertSame(today()->toDateString(), $drill->fresh()->inspection_date->toDateString());
        $this->assertSame(today()->addMonths(6)->toDateString(), $drill->fresh()->next_inspection_date->toDateString());

        $this->assertSame(AssetCheckResult::NotOk, $ladderItem->fresh()->result);
        $this->assertSame('Sport gebroken', $ladderItem->fresh()->remark);
        $this->assertSame('2025-01-10', $ladder->fresh()->inspection_date->toDateString(), 'Not OK keeps the old control date.');
        $this->assertSame([1, 2], [$check->fresh()->notOkCount(), $check->fresh()->checkedCount()]);

        // Once closed nothing can be changed any more.
        $check->update(['closed_at' => now()]);
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $check->fresh(), 'pageClass' => ViewAssetCheck::class])
            ->call('updateTableColumnState', 'result', (string) $ladderItem->getKey(), 'ok');
        $this->assertSame(AssetCheckResult::NotOk, $ladderItem->fresh()->result);
    }

    #[Test]
    public function new_equipment_can_be_added_to_an_open_list(): void
    {
        $check = $this->publish(User::factory()->admin()->create());
        $this->assertSame(0, $check->items()->count());

        Asset::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(ViewAssetCheck::class, ['record' => $check->getRouteKey()])->callAction('sync');
        $this->assertSame(1, $check->items()->count());
    }

    #[Test]
    public function the_control_lists_follow_the_assets_and_teams_modules(): void
    {
        $this->publish(User::factory()->admin()->create());

        $this->setModules(['assets' => false]);
        $this->actingAs($this->member)->get('/asset-checks')->assertForbidden();
    }
}
