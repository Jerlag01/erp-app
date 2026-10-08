<?php

namespace Tests\Feature;

use App\Enums\AssetLogType;
use App\Enums\AssetStatus;
use App\Filament\Resources\AssetLogs\Pages\ManageAssetLogs;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\LogsRelationManager;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLog;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_damage_marks_the_asset_as_damaged_and_a_linked_repair_puts_it_back_in_service(): void
    {
        $asset = Asset::factory()->create();

        $damage = AssetLog::factory()->for($asset)->damage()->create();
        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);

        AssetLog::factory()->repairOf($damage)->create(['date' => today()]);

        $this->assertNotNull($damage->fresh()->resolved_at);
        $this->assertSame(AssetStatus::InService, $asset->fresh()->status);
    }

    #[Test]
    public function the_asset_stays_damaged_while_another_damage_is_open(): void
    {
        $asset = Asset::factory()->create();
        $first = AssetLog::factory()->for($asset)->damage()->create();
        AssetLog::factory()->for($asset)->damage()->create();

        $first->resolve();

        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);
    }

    #[Test]
    public function retired_or_out_of_service_assets_keep_their_status(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Retired]);

        AssetLog::factory()->for($asset)->damage()->create();

        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
    }

    #[Test]
    public function reopening_a_damage_marks_the_asset_damaged_again(): void
    {
        $asset = Asset::factory()->create();
        $damage = AssetLog::factory()->for($asset)->damage()->create();
        $damage->resolve();
        $this->assertSame(AssetStatus::InService, $asset->fresh()->status);

        $damage->reopen();

        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);
    }

    #[Test]
    public function an_inspection_moves_the_inspection_dates_forward_using_the_category_interval(): void
    {
        $category = AssetCategory::factory()->create(['inspection_interval_months' => 6]);
        $asset = Asset::factory()->for($category, 'category')->create([
            'inspection_date' => '2025-01-10',
            'next_inspection_date' => '2025-07-10',
        ]);

        AssetLog::factory()->for($asset)->create(['type' => AssetLogType::Inspection, 'date' => '2025-07-01']);

        $asset->refresh();
        $this->assertSame('2025-07-01', $asset->inspection_date->toDateString());
        $this->assertSame('2026-01-01', $asset->next_inspection_date->toDateString());
    }

    #[Test]
    public function only_repairs_link_to_a_damage_and_only_damages_have_a_severity(): void
    {
        $damage = AssetLog::factory()->damage()->create();

        $note = AssetLog::factory()->for($damage->asset)->create(['damage_id' => $damage->id, 'severity' => 'major']);

        $this->assertNull($note->damage_id);
        $this->assertNull($note->severity);
        $this->assertNull($damage->fresh()->resolved_at);
    }

    #[Test]
    public function a_repair_can_be_registered_from_an_open_damage(): void
    {
        $team = Team::factory()->create();
        $this->actingAs($user = User::factory()->withPermissions(['asset_logs' => ['view', 'create']])->inTeam($team)->create());
        Filament::setCurrentPanel('app');
        $damage = AssetLog::factory()->for(Asset::factory()->for($team))->damage()->create(['title' => 'Behuizing gebarsten']);

        Livewire::test(ManageAssetLogs::class)
            ->assertTableActionVisible('registerRepair', $damage)
            ->callTableAction('registerRepair', $damage, ['performed_by' => 'Fluke Service', 'cost' => 85])
            ->assertHasNoTableActionErrors();

        $repair = AssetLog::where('type', AssetLogType::Repair)->sole();
        $this->assertSame($damage->id, $repair->damage_id);
        $this->assertSame($user->id, $repair->user_id);
        $this->assertSame('85.00', $repair->cost);
        $this->assertNotNull($damage->fresh()->resolved_at);
        $this->assertSame(AssetStatus::InService, $damage->asset->fresh()->status);
    }

    #[Test]
    public function a_damage_can_be_logged_from_the_asset_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        $asset = Asset::factory()->create();

        Livewire::test(LogsRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => ViewAsset::class])
            ->callTableAction('create', data: ['type' => 'damage', 'title' => 'Display defect', 'severity' => 'critical'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);
        $this->assertSame('Display defect', $asset->openDamages()->sole()->title);
    }
}
