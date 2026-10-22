<?php

namespace Tests\Feature;

use App\Actions\Timesheets\TimesheetPdf;
use App\Models\ChargingStation;
use App\Models\User;
use App\Models\WasteEntry;
use App\Support\ChargingExport;
use App\Support\Timesheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pdfs_are_made_in_the_language_of_the_user(): void
    {
        WasteEntry::factory()->create(['date' => '2026-03-01']);
        $locales = [];
        Event::listen('composing: waste.totals-pdf', function () use (&$locales): void {
            $locales[] = app()->getLocale();
        });

        foreach (['fr', 'en', 'nl'] as $locale) {
            $user = User::factory()->withPermissions(['waste' => ['view', 'generate_pdf']])->inTeam()->create(['locale' => $locale]);
            $this->actingAs($user)->get(route('waste.totals-pdf', ['from' => '2026-01-01', 'until' => '2026-12-31']))->assertOk();
        }

        $this->assertSame(['fr', 'en', 'nl'], $locales);
    }

    #[Test]
    public function the_prestation_sheet_and_the_charging_export_are_translated(): void
    {
        $user = User::factory()->create(['name' => 'Jan Peeters']);
        $monday = Timesheets::monday(now()->toImmutable());
        $pdf = app(TimesheetPdf::class);

        app()->setLocale('fr');
        $cells = $pdf->cells($user, app(Timesheets::class)->week($user, $monday));
        $this->assertSame('Nom :', $cells[2]['A']);
        $this->assertSame('Pause de midi', $cells[7 + 6]['F']);
        $this->assertSame('lundi', $cells[7 + 4]['A']);

        // A PDF in another language than the current one leaves the current one alone.
        $this->assertStringStartsWith('%PDF', $pdf->render($user, $monday, 'en'));
        $this->assertSame('fr', app()->getLocale());

        // The Excel keeps the client's column names; the PDF translates them.
        $html = '';
        Event::listen('composing: charging.export-pdf', function ($view) use (&$html): void {
            $html = implode('|', $view->getData()['headers']);
        });
        ChargingStation::factory()->create();
        app(ChargingExport::class)->pdf('Laadpalen', ChargingExport::stationColumns(false), ChargingStation::all());
        $this->assertStringContainsString('Où ?', $html);
        $this->assertStringContainsString('Entièrement exécuté', $html);
        $this->assertArrayHasKey('Waar?', ChargingExport::stationColumns(false));
    }
}
