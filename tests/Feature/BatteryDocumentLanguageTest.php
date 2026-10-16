<?php

namespace Tests\Feature;

use App\Actions\Batteries\BatteryDocument;
use App\Models\BatteryDossier;
use App\Models\EnersysDossier;
use App\Models\Project;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatteryDocumentLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function project(string $category, string $postalCode, string $area): Project
    {
        $site = WorkSite::factory()->create([
            'postal_code' => $postalCode, 'city' => 'Ergens', 'street' => 'Straat', 'house_number' => '1',
            'work_site_area_id' => WorkSiteArea::query()->firstOrCreate(['name' => $area])->id,
        ]);

        return Project::factory()->inCategory($category)->for($site)->create();
    }

    #[Test]
    public function the_language_follows_the_region_of_the_cow_site(): void
    {
        $this->assertSame('nl', BatteryDossier::regionLocale('9000'), 'Oost-Vlaanderen');
        $this->assertSame('nl', BatteryDossier::regionLocale('2160'), 'Antwerpen');
        $this->assertSame('nl', BatteryDossier::regionLocale('3000'), 'Vlaams-Brabant');
        $this->assertSame('fr', BatteryDossier::regionLocale('1300'), 'Brabant wallon');
        $this->assertSame('fr', BatteryDossier::regionLocale('5000'), 'Namur');
        $this->assertSame('fr', BatteryDossier::regionLocale('7000'), 'Hainaut');
        $this->assertSame('fr', BatteryDossier::regionLocale('1000'), 'Brussels: French by default');
        $this->assertSame('fr', BatteryDossier::regionLocale(null, '4-WAL'), 'Without postal code: the area');
        $this->assertSame('nl', BatteryDossier::regionLocale(null, '2-VLA'));

        settings()->save('batteries', [...settings('batteries'), 'brussels_locale' => 'nl']);
        $this->assertSame('nl', BatteryDossier::regionLocale('1050'));
    }

    #[Test]
    public function the_604_documents_are_printed_in_the_language_of_the_region(): void
    {
        $documents = app(BatteryDocument::class);
        $walloon = BatteryDossier::factory()->create(['project_id' => $this->project('604', '5000', '4-WAL')->id, 'old_count' => 2, 'old_capacity_ah' => 800, 'new_count' => 2, 'new_capacity_ah' => 500]);
        $flemish = BatteryDossier::factory()->create(['project_id' => $this->project('604', '9000', '1-VLA')->id]);

        $html = $documents->html($walloon, 'delivery');
        $this->assertStringContainsString('Bon de livraison', $html);
        $this->assertStringContainsString('Transport vertical : montage', $html);
        $this->assertStringNotContainsString('Leveringsnota', $html);
        $this->assertStringContainsString('Rapport de mesure', $documents->html($walloon, 'measurement'));
        $inspection = $documents->html($walloon, 'inspection');
        $this->assertStringContainsString('Rapport d&#039;inspection visuelle', $inspection);
        $this->assertStringContainsString('Tous les éléments sont-ils identiques ?', $inspection);
        $this->assertStringContainsString('Oui', $inspection);

        $this->assertStringContainsString('Leveringsnota', $documents->html($flemish, 'delivery'));
        $this->assertStringContainsString('Zijn alle cellen identiek?', $documents->html($flemish, 'inspection'));

        // The invoice request is for our own administration: always Dutch.
        $this->assertSame('nl', $documents->locale($walloon, 'invoice_request'));

        // Set by hand on the dossier.
        $walloon->update(['document_locale' => 'nl']);
        $this->assertStringContainsString('Leveringsnota', $documents->html($walloon->fresh(), 'delivery'));

        // The language of the screen is not changed by printing.
        $this->assertSame('nl', app()->getLocale());
    }

    #[Test]
    public function the_605_enersys_documents_are_printed_in_the_language_of_the_region(): void
    {
        $documents = app(BatteryDocument::class);
        $walloon = EnersysDossier::factory()->create(['project_id' => $this->project('605', '4000', '5-WAL')->id]);
        $flemish = EnersysDossier::factory()->create(['project_id' => $this->project('605', '2160', '2-VLA')->id]);

        $delivery = $documents->html($walloon, 'delivery');
        $this->assertStringContainsString('Bon de livraison', $delivery);
        $this->assertStringContainsString('Enlèvement de 2 sets de batteries de 155/100 Ah', $delivery);
        $this->assertStringContainsString('Bacs de rétention', $delivery);
        $measurement = $documents->html($walloon, 'measurement');
        $this->assertStringContainsString('Tension de floating', $measurement);
        $this->assertStringContainsString('(En floating)', $measurement);
        $this->assertStringContainsString('Les portes des armoires s&#039;ouvrent-elles complètement', $documents->html($walloon, 'inspection'));

        $this->assertStringContainsString('Afhalen van 2 sets batterijen van 155/100 Ah', $documents->html($flemish, 'delivery'));
        $this->assertStringContainsString('(In vlotting)', $documents->html($flemish, 'measurement'));

        $this->assertSame('nl', $documents->locale($walloon, 'order'), 'The order is internal.');
        $this->assertNotEmpty($documents->render($walloon, 'delivery'));
    }
}
