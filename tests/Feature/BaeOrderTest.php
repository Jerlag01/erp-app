<?php

namespace Tests\Feature;

use App\Actions\Batteries\BaeOrderDocument;
use App\Actions\Batteries\CreateBaeOrder;
use App\Enums\BatteryMounting;
use App\Enums\ProjectStatus;
use App\Filament\Resources\BaeOrders\BaeOrderResource;
use App\Filament\Resources\BaeOrders\Pages\EditBaeOrder;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaeOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    /**
     * @param  list<string>  $battery
     */
    protected function user(array $battery = ['view', 'bae_orders', 'generate_pdf']): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'batteries' => $battery])->inTeam($this->team)->create(['name' => 'Jeroen Lagaet']);
    }

    protected function project(string $category = '604'): Project
    {
        $site = WorkSite::query()->where('cow_code', '83HVR')->first() ?? WorkSite::factory()->create(['cow_code' => '83HVR', 'street' => 'Route de Barvaux,', 'house_number' => '180', 'postal_code' => '5590', 'city' => 'HAVERSIN']);
        $project = Project::factory()->inCategory($category)->for($site)->create(['number' => 923, 'status' => ProjectStatus::Offer]);
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function a_new_604_project_starts_with_its_bae_order(): void
    {
        $this->travelTo('2026-07-14 10:00:00');
        $this->actingAs($this->user());

        $project = $this->project();
        $order = $project->baeOrder;

        $this->assertNotNull($order);
        $this->assertSame('604/923/0726/JL', $order->order_number);
        $this->assertSame('2026-07-14', $order->order_date->toDateString());
        $this->assertSame('Belgacom CSL/PUR/GAE/4600001931', $order->contract);
        $this->assertSame('PRIO ASAP', $order->delivery_date);
        $this->assertSame('Prox Batt BAE 604/923 - 83HVR', $order->ourReference());
        $this->assertSame('83HVR : Route de Barvaux, 180 - 5590 HAVERSIN', $order->cowLine());

        // Other categories get none.
        $this->assertNull($this->project('600')->baeOrder);
    }

    #[Test]
    public function the_form_fills_the_lines_from_the_type_and_mounting_and_totals_like_the_excel(): void
    {
        $this->actingAs($this->user());
        $order = $this->project()->baeOrder;
        $model = BatteryModel::query()->where('name', '7 OPzV 490')->value('id');

        Livewire::test(EditBaeOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['battery_model_id' => $model, 'mounting' => 'u_vorm'])
            ->assertSchemaStateSet([
                'cell_price' => 214.52,
                'lines.intercell.code' => '4364117 (90x175)',
                'lines.intercell.per_battery' => 22,
                'lines.step.code' => '4435450 (50x450)',
                'lines.tier70.per_battery' => 2,
                'lines.rack.code' => 'PGL2-23H',
                'lines.rack.price' => 281.0,
            ])
            ->fillForm(['battery_count' => 2, 'cells_per_battery' => 24, 'capacity_ah' => 500, 'technology' => 'OPzV'])
            ->assertSeeHtml('data-order-total')
            ->assertSee('€ 10.858,96')
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        // Excel Order: 2 × 24 × 214,52 = 10.296,96 plus 2 racks of 281 = 10.858,96.
        $this->assertSame(10296.96, $order->batteriesTotal());
        $this->assertSame(562.0, $order->rackTotal());
        $this->assertSame(10858.96, $order->total());
        $this->assertSame(44, $order->lineTotal('intercell'));
        $this->assertSame(276, $order->lineTotal('measuring_pole'));
        $this->assertSame('48V / 7 OPzV 490', $order->batteryTitle());
        $this->assertSame('U-Vorm: PGL2-23H', $order->rackDescription());
        $this->assertSame('Batterie: 2 x 7 OPzV 490', $order->subject());
    }

    #[Test]
    public function the_order_takes_over_the_new_batteries_of_the_dossier(): void
    {
        $this->actingAs($this->user());
        $project = $this->project();
        BatteryDossier::factory()->create([
            'project_id' => $project->id,
            'battery_model_id' => BatteryModel::query()->where('name', '8 OPzV 800')->value('id'),
            'mounting' => 'trap',
            'new_count' => 3,
            'new_capacity_ah' => 800,
        ]);

        Livewire::test(EditBaeOrder::class, ['record' => $project->baeOrder->getRouteKey()])
            ->callAction('fromDossier')
            ->assertSchemaStateSet(['battery_count' => 3, 'capacity_ah' => 800, 'mounting' => BatteryMounting::Steps, 'lines.rack.code' => 'SGL2-27H', 'lines.intercell.per_battery' => 44])
            // 8 OPzV 800 has no BAE price per cell in the catalogue yet: it is asked.
            ->call('save')
            ->assertHasFormErrors(['cell_price' => 'required'])
            ->fillForm(['cell_price' => 290])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $project->baeOrder->refresh()->battery_count);
    }

    #[Test]
    public function the_pdf_is_exported_only_with_the_permissions(): void
    {
        $order = $this->project()->baeOrder;

        $this->actingAs($this->user())
            ->get(route('batteries.bae-order-pdf', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotNull($order->refresh()->exported_at);

        $this->actingAs($this->user(['view', 'bae_orders']))->get(route('batteries.bae-order-pdf', $order))->assertForbidden();
        $this->actingAs($this->user(['view', 'generate_pdf']))->get(route('batteries.bae-order-pdf', $order))->assertForbidden();

        // Without "bae_orders" the module is not reachable at all.
        $this->get(BaeOrderResource::getUrl('index'))->assertForbidden();
        $this->get(BaeOrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
    }

    #[Test]
    public function the_pdf_is_laid_out_like_the_excel_order_sheet(): void
    {
        $this->actingAs($this->user());
        $order = $this->project()->baeOrder;
        $model = BatteryModel::query()->where('name', '7 OPzV 490')->value('id');
        $order->update([...BaeOrder::factory()->raw(['project_id' => $order->project_id]), ...CreateBaeOrder::fromCatalogue($model, 'u_vorm')]);

        $html = app(BaeOrderDocument::class)->html($order->refresh());

        $this->assertStringContainsString('Prox Batt BAE 604/923 - 83HVR', $html);
        $this->assertStringContainsString('Intercell connector', $html);
        $this->assertStringContainsString('4364117 (90x175)', $html);
        $this->assertStringContainsString('10.858,96', $html);
        $this->assertStringContainsString('Orders will be checked by BAE', $html);
    }

    #[Test]
    public function the_pdf_is_saved_as_the_order_document_of_the_delivery(): void
    {
        Storage::fake(BatteryDelivery::DISK);
        $this->actingAs($this->user(['view', 'bae_orders', 'generate_pdf', 'documents', 'documents_upload']));
        $project = $this->project();
        $delivery = BatteryDelivery::factory()->create(['project_id' => $project->id]);

        // The form is saved first, so it has to be complete.
        Livewire::test(EditBaeOrder::class, ['record' => $project->baeOrder->getRouteKey()])
            ->fillForm(['battery_model_id' => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'), 'mounting' => 'u_vorm'])
            ->callAction('toDelivery')
            ->assertNotified();

        $delivery->refresh();
        $this->assertTrue($delivery->hasDocument('order'));
        Storage::disk(BatteryDelivery::DISK)->assertExists($delivery->order_document);

        // Without the upload permission the action is not offered.
        $this->actingAs($this->user(['view', 'bae_orders', 'generate_pdf', 'documents']));
        Livewire::test(EditBaeOrder::class, ['record' => $project->baeOrder->getRouteKey()])->assertActionHidden('toDelivery');
    }

    #[Test]
    public function older_604_projects_get_their_order_from_the_project_page(): void
    {
        $this->actingAs($this->user());
        $project = $this->project();
        $project->baeOrder->delete();

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('baeOrder')
            ->callAction('baeOrder')
            ->assertRedirect(BaeOrderResource::getUrl('edit', ['record' => $project->refresh()->baeOrder]));

        $this->actingAs($this->user(['view']));
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('baeOrder');
    }
}
