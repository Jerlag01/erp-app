<?php

namespace Tests\Feature;

use App\Actions\It\AuditAsset;
use App\Actions\It\CheckinAsset;
use App\Actions\It\CheckoutAsset;
use App\Enums\ItLogAction;
use App\Filament\Resources\ItAssets\Pages\ViewItAsset;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItLicense;
use App\Models\ItStatusLabel;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItAssetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_asset_is_checked_out_to_a_user_and_checked_in_with_history(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $asset = ItAsset::factory()->create();
        $employee = User::factory()->create(['name' => 'Eva']);

        app(CheckoutAsset::class)->handle($asset, $employee, today()->addMonth()->toDateString(), 'Nieuwe laptop');

        $asset->refresh();
        $this->assertTrue($asset->isCheckedOut());
        $this->assertSame('Eva', $asset->assignedName());

        $broken = ItStatusLabel::factory()->undeployable()->create();
        app(CheckinAsset::class)->handle($asset, $broken->id, note: 'Scherm kapot');

        $asset->refresh();
        $this->assertFalse($asset->isCheckedOut());
        $this->assertSame($broken->id, $asset->it_status_label_id);
        $this->assertSame([ItLogAction::Checkin, ItLogAction::Checkout], $asset->logs->pluck('action')->all());
        $this->assertTrue($asset->logs->last()->target->is($employee));
        $this->assertTrue($asset->logs->first()->user->is($admin));
    }

    #[Test]
    public function only_available_deployable_assets_can_go_out(): void
    {
        $user = User::factory()->create();
        $broken = ItAsset::factory()->for(ItStatusLabel::factory()->undeployable(), 'status')->create();
        $this->assertCheckoutRefused(fn () => app(CheckoutAsset::class)->handle($broken, $user));

        $asset = ItAsset::factory()->create();
        app(CheckoutAsset::class)->handle($asset, $user);
        $this->assertCheckoutRefused(fn () => app(CheckoutAsset::class)->handle($asset->fresh(), User::factory()->create()));

        $alone = ItAsset::factory()->create();
        $this->assertCheckoutRefused(fn () => app(CheckoutAsset::class)->handle($alone, $alone));
    }

    #[Test]
    public function an_asset_can_go_to_a_location_or_onto_another_asset(): void
    {
        $location = Location::factory()->create();
        $desktop = ItAsset::factory()->create();
        $monitor = ItAsset::factory()->create();
        $spare = ItAsset::factory()->create();

        app(CheckoutAsset::class)->handle($monitor, $desktop);
        app(CheckoutAsset::class)->handle($spare, $location);

        $this->assertTrue($desktop->children()->first()->is($monitor));
        $this->assertSame($location->id, $spare->fresh()->location_id);
    }

    #[Test]
    public function an_audit_plans_the_next_one(): void
    {
        Carbon::setTestNow('2026-10-08');
        $asset = ItAsset::factory()->create();

        app(AuditAsset::class)->handle($asset);

        $this->assertSame('2026-10-08', $asset->fresh()->last_audit_date->toDateString());
        $this->assertSame('2027-10-08', $asset->fresh()->next_audit_date->toDateString());
    }

    #[Test]
    public function warranty_and_end_of_life_follow_the_purchase_date(): void
    {
        $asset = ItAsset::factory()->create(['purchase_date' => '2025-01-15', 'warranty_months' => 36]);

        $this->assertSame('2028-01-15', $asset->warrantyExpires()->toDateString());
        $this->assertSame('2029-01-15', $asset->endOfLife()->toDateString());
    }

    #[Test]
    public function employees_see_their_teams_assets_and_check_out_from_the_page(): void
    {
        $location = Location::factory()->create();
        $team = Team::factory()->create(['location_id' => $location->id]);
        $employee = User::factory()->withPermissions(['it_assets' => ['view', 'checkout']])->inTeam($team)->create();
        $colleague = User::factory()->inTeam($team)->create();
        $atDepot = ItAsset::factory()->create(['location_id' => $location->id]);
        $elsewhere = ItAsset::factory()->create(['asset_tag' => 'IT-ELSEWHERE']);

        $this->actingAs($employee)->get('/it-assets')->assertOk()->assertSee($atDepot->asset_tag)->assertDontSee('IT-ELSEWHERE');
        $this->actingAs($employee)->get("/it-assets/{$elsewhere->id}")->assertNotFound();

        Filament::setCurrentPanel('app');
        Livewire::actingAs($employee)
            ->test(ViewItAsset::class, ['record' => $atDepot->getRouteKey()])
            ->callAction('checkout', ['target_type' => 'user', 'user_id' => $colleague->id])
            ->assertHasNoActionErrors();

        $this->assertTrue($atDepot->fresh()->assigned->is($colleague));
    }

    #[Test]
    public function my_it_shows_what_is_checked_out_to_me(): void
    {
        $me = User::factory()->create();
        $mine = ItAsset::factory()->create(['asset_tag' => 'IT-MINE']);
        ItAsset::factory()->create(['asset_tag' => 'IT-NOTMINE']);
        app(CheckoutAsset::class)->handle($mine, $me);

        $this->actingAs($me)->get('/my-it')->assertOk()->assertSee('IT-MINE')->assertDontSee('IT-NOTMINE');
    }

    #[Test]
    public function the_qr_code_and_labels_lead_to_the_asset(): void
    {
        $asset = ItAsset::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->get("/it/{$asset->id}")->assertRedirect("/it-assets/{$asset->id}");
        $this->actingAs($admin)->get(route('it.labels', ['assets' => [$asset->id]]))->assertOk()->assertSee($asset->asset_tag)->assertSee('<svg', false);
    }

    #[Test]
    public function the_it_module_can_be_switched_off(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = ItAsset::factory()->create();

        $this->setModules(['it' => false]);

        foreach (['/admin/it-assets', '/admin/it-licenses', '/admin/it-accessories', '/admin/it-models', '/my-it'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }

        $this->get("/it/{$asset->id}")->assertNotFound();
    }

    #[Test]
    public function every_it_page_renders(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();
        $asset = ItAsset::factory()->create();
        app(CheckoutAsset::class)->handle($asset, $admin);
        $license = ItLicense::factory()->create();
        $item = ItItem::factory()->create();

        foreach (['/admin', ''] as $prefix) {
            foreach (['/it-assets', '/it-assets/create', "/it-assets/{$asset->id}", "/it-assets/{$asset->id}/edit",
                '/it-licenses', '/it-licenses/create', "/it-licenses/{$license->id}", "/it-licenses/{$license->id}/edit",
                '/it-accessories', '/it-accessories/create', "/it-accessories/{$item->id}", '/it-consumables', '/it-components',
                '/it-maintenances', '/it-models', '/it-categories', '/it-status-labels'] as $url) {
                $this->actingAs($admin)->get($prefix.$url)->assertOk();
            }
        }

        $this->actingAs($admin)->get('/my-it')->assertOk()->assertSee($asset->asset_tag);
    }

    protected function assertCheckoutRefused(callable $callback): void
    {
        try {
            $callback();
            $this->fail('The checkout should have been refused.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
