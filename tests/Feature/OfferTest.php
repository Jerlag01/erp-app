<?php

namespace Tests\Feature;

use App\Actions\Finance\OfferPdf;
use App\Enums\InvoiceStatus;
use App\Enums\OfferStatus;
use App\Enums\ProjectFileSection;
use App\Enums\VatRate;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\OffersRelationManager;
use App\Models\Client;
use App\Models\Offer;
use App\Models\OfferCategory;
use App\Models\Project;
use App\Models\StandardOfferLine;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Modules;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Offers for projects: categories, default lines, the totals of the Proximus offer template, the PDF
 * and the invoice made from an accepted offer.
 */
class OfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        app(Settings::class)->save('finance', [...Settings::defaults()['finance'], 'company_name' => 'Power Installation nv', 'vat_number' => 'BE0462401968']);
    }

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        return $admin;
    }

    protected function project(): Project
    {
        return Project::factory()->create([
            'short_description' => 'Omvormer Rodin 10 sites',
            'work_site_id' => WorkSite::factory()->create(['cow_code' => '02NO1', 'street' => 'Mercuriusstraat', 'house_number' => '27', 'postal_code' => '1930', 'city' => 'Zaventem']),
        ]);
    }

    #[Test]
    public function offers_need_the_offers_permission_and_the_template_categories_and_lines_are_there(): void
    {
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create();
        $this->actingAs($employee)->get('/offers')->assertForbidden();
        $this->actingAs($employee)->get('/offer-categories')->assertForbidden();

        $sales = User::factory()->withPermissions(['offers' => ['view', 'create', 'update']])->inTeam()->create();
        $this->actingAs($sales)->get('/offers')->assertOk();
        $this->actingAs($sales)->get('/offers/create')->assertOk();

        $this->actingAs(User::factory()->withPermissions(['offer_master_data' => ['view']])->inTeam()->create())
            ->get('/standard-offer-lines')->assertOk()->assertSee('DC-AC power inverter TS-400-248B (400W)');

        $this->assertSame(['A', 'C', 'E', 'G'], OfferCategory::query()->ordered()->pluck('code')->all());
        $this->assertTrue(OfferCategory::query()->where('code', 'G')->value('is_general_costs'));
    }

    #[Test]
    public function an_offer_for_a_project_adds_default_lines_and_computes_supply_installation_general_costs_and_workdays(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['company_name' => 'Proximus NV']);
        $project = $this->project();
        $undo = Repeater::fake();

        $page = Livewire::withQueryParams(['project' => $project->id])->test(CreateOffer::class)
            ->assertSchemaStateSet([
                'project_id' => $project->id,
                'subject' => 'Omvormer Rodin 10 sites',
                'client_reference' => '02NO1 ZAVENTEM',
                'site_address' => "Proximus 02NO1\nMercuriusstraat 27\n1930 ZAVENTEM",
                'hourly_rate' => 68.64,
            ]);

        // Category A as a whole, and single lines of C, E and G (the labour line takes the hourly rate).
        $c = fn (string $description): int => StandardOfferLine::query()->where('description', 'like', $description.'%')->value('id');
        $page->callAction(TestAction::make('addStandardLines')->schemaComponent('offer_actions'), [
            'categories' => [OfferCategory::query()->where('code', 'A')->value('id')],
            'lines' => [$c('XGB'), $c('KB - omvormer'), $c('HO7Z1'), $c('Labelling kabels'), $c('Labelling racks'), $c('Overschakeling'), $c('Minprijs'), $c('Studie')],
        ])->assertHasNoActionErrors();

        $lines = collect($page->get('data.lines'));
        $this->assertCount(12, $lines);
        $this->assertSame(68.64, $lines->firstWhere('description', 'Overschakeling van verbruikers (per uur)')['install_price']);

        // Quantities as in the template.
        $state = $lines->map(fn (array $line): array => [...$line, 'quantity' => match (true) {
            str_starts_with($line['description'], 'XGB') => 20,
            str_starts_with($line['description'], 'HO7Z1') => 3,
            str_starts_with($line['description'], 'Overschakeling') => 2,
            default => 1,
        }])->all();

        $page->fillForm(['client_id' => $client->id, 'our_reference' => '600/KI/091', 'lines' => $state])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $offer = Offer::query()->sole();
        $this->assertSame('OF2026-0001', $offer->number);
        $this->assertSame($admin->id, $offer->created_by);
        $this->assertSame(OfferStatus::Draft, $offer->status);
        // Supply 458,31 (A) + 82,74 (C) − 5,00 (E); installation 14,83 + 2 × 68,64; general costs apart.
        $this->assertSame('536.05', $offer->supply_total);
        $this->assertSame('152.11', $offer->install_total);
        $this->assertSame('196.60', $offer->general_total);
        $this->assertSame('884.76', $offer->total);
        $this->assertSame(2.22, $offer->labourHours());
        $this->assertSame([2 => 0.14, 3 => 0.09, 4 => 0.07], $offer->workdays());
        $this->assertSame(['A', 'C', 'E'], $offer->chapters()->map(fn (array $chapter): string => $chapter['category']->code)->all());
        $this->assertSame(['G'], $offer->chapters(true)->map(fn (array $chapter): string => $chapter['category']->code)->all());

        // The PDF, and its copy in the project's offers (a new version the second time).
        $this->get(route('finance.offer-pdf', $offer))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $html = view('finance.offer-pdf', ['offer' => $offer->fresh(['lines.category', 'project.workSite', 'client'])])->render();
        foreach (['02NO1 ZAVENTEM', '600/KI/091', 'Plaatsen omvormer', '€ 536,05', '€ 152,11', '€ 688,16', '€ 884,76', '2,22', '0,14 met 2 man', 'Kabelladders'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        Livewire::test(EditOffer::class, ['record' => $offer->id])->callAction('saveToProject');
        Livewire::test(EditOffer::class, ['record' => $offer->id])->callAction('saveToProject');
        $file = $project->files()->where('section', ProjectFileSection::Offers->value)->sole();
        $this->assertSame(OfferPdf::filename($offer), $file->name);
        $this->assertSame('02NO1 Offerte OF2026-0001.pdf', $file->name);
        $this->assertSame(2, $file->fresh()->version);
    }

    #[Test]
    public function an_accepted_offer_becomes_a_draft_invoice_and_is_then_locked(): void
    {
        $this->admin();
        $client = Client::factory()->create(['default_vat_rate' => VatRate::Six]);
        $offer = Offer::factory()->create(['client_id' => $client->id, 'project_id' => $this->project()->id]);
        $works = OfferCategory::factory()->create(['code' => 'A']);
        $general = OfferCategory::factory()->generalCosts()->create(['code' => 'G']);
        $offer->lines()->createMany([
            ['offer_category_id' => $works->id, 'description' => 'Omvormer', 'quantity' => 2, 'unit' => 'st', 'supply_price' => 100, 'install_price' => 25],
            ['offer_category_id' => $works->id, 'description' => 'Overschakeling van verbruikers'],
            ['offer_category_id' => $general->id, 'description' => 'Studie', 'quantity' => 1, 'supply_price' => 50],
        ]);
        $offer->recalculate();

        Livewire::test(EditOffer::class, ['record' => $offer->id])
            ->callAction('createInvoice')
            ->assertHasNoActionErrors();

        $offer->refresh();
        $invoice = $offer->invoice;
        $this->assertSame(OfferStatus::Accepted, $offer->status);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($offer->project_id, $invoice->project_id);
        // The text line is left out; supply + installation per line; the client's VAT rate.
        $this->assertSame(['A · Omvormer', 'G · Studie'], $invoice->lines->pluck('description')->all());
        $this->assertSame(['125.00', '50.00'], $invoice->lines->pluck('unit_price')->all());
        $this->assertSame(VatRate::Six, $invoice->lines->first()->vat_rate);
        $this->assertSame('300.00', $invoice->subtotal);

        // Invoiced: read-only, not deleted, no second invoice.
        Livewire::test(EditOffer::class, ['record' => $offer->id])
            ->assertActionHidden('createInvoice')
            ->assertActionHidden('delete')
            ->assertFormFieldDisabled('subject');
    }

    #[Test]
    public function offers_work_without_the_finance_module_and_can_be_switched_off(): void
    {
        $this->admin();
        settings()->save('modules', [...settings('modules'), Modules::FINANCE => false]);
        settings()->save('offers', [...Settings::defaults()['offers'], 'company_name' => 'Power Installation nv', 'hourly_rate' => 70]);
        $project = $this->project();

        // No finance clients: a client name, the hourly rate of the offer settings; no invoice.
        $this->get('/offers')->assertOk();
        $this->get('/invoices')->assertForbidden();
        Livewire::withQueryParams(['project' => $project->id])->test(CreateOffer::class)
            ->assertSchemaStateSet(['hourly_rate' => 70.0])
            ->assertFormFieldHidden('client_id')
            ->fillForm(['client_name' => 'Proximus NV'])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::query()->sole();
        $this->assertSame(['Proximus NV', null], [$offer->clientLabel(), $offer->client_id]);
        $offer->lines()->create(['offer_category_id' => OfferCategory::query()->value('id'), 'description' => 'Omvormer', 'quantity' => 1, 'supply_price' => 100]);
        Livewire::test(EditOffer::class, ['record' => $offer->id])->assertActionHidden('createInvoice');
        $html = view('finance.offer-pdf', ['offer' => $offer->fresh(['lines.category', 'project.workSite', 'client'])])->render();
        $this->assertStringContainsString('Proximus NV', $html);
        $this->assertStringContainsString('Power Installation nv', $html);

        // The offers module off: gone everywhere, also from the project, whatever finance does.
        settings()->save('modules', [...settings('modules'), Modules::FINANCE => true, Modules::OFFERS => false]);
        foreach (['/offers', '/offer-categories', '/standard-offer-lines', route('finance.offer-pdf', $offer)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/invoices')->assertOk();
        Livewire::test(OffersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionHidden(TestAction::make('makeOffer')->table());
    }

    #[Test]
    public function the_project_offers_tab_links_to_a_new_offer_for_the_project(): void
    {
        $this->admin();
        $project = $this->project();

        Livewire::test(OffersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionVisible(TestAction::make('makeOffer')->table())
            ->assertActionHasUrl(TestAction::make('makeOffer')->table(), route('filament.app.resources.offers.create', ['project' => $project->id]))
            ->assertActionHidden(TestAction::make('projectOffers')->table());

        Offer::factory()->create(['project_id' => $project->id]);

        Livewire::test(OffersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionVisible(TestAction::make('projectOffers')->table());
    }
}
