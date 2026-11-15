<?php

namespace Tests\Feature;

use App\Filament\Pages\StockQrExportPage;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\Team;
use App\Models\User;
use App\Support\StockQrExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The QR codes of the stock items as Excel for Labelife (label 99010).
 */
class StockQrExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<list<mixed>>
     */
    protected function rows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }

    #[Test]
    public function the_qr_codes_of_the_stock_items_are_exported_for_labelife(): void
    {
        Filament::setCurrentPanel('app');
        $location = Location::factory()->create();
        $this->actingAs(User::factory()->withPermissions(['stock_items' => ['view']])->inTeam(Team::factory()->create(['location_id' => $location->id]))->create());
        $cable = StockItem::factory()->create(['name' => 'XVB 3G2,5', 'name_fr' => 'Câble XVB 3G2,5', 'manufacturer_reference' => 'XVB325']);
        $breaker = StockItem::factory()->create(['name' => 'Automaat 2P 16A']);
        StockItem::factory()->create(['name' => 'Oud artikel', 'is_active' => false]);
        StockLevel::query()->where(['stock_item_id' => $cable->id, 'location_id' => $location->id])->update(['quantity' => 5]);

        $export = app(StockQrExport::class);
        $rows = $this->rows($export->xlsx(['active' => true], 'fr'));
        // Plain header row, one row per active item: the QR column holds the scan link.
        $this->assertSame(['QR', 'Naam', 'Referentie', 'Fabrikant', 'Eenheid', 'Categorie', 'EAN', 'Artikel-ID'], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame([route('stock.qr', $breaker), 'Automaat 2P 16A'], array_slice($rows[1], 0, 2));
        $this->assertSame([route('stock.qr', $cable), 'Câble XVB 3G2,5', 'XVB325'], array_slice($rows[2], 0, 3));

        // Only the items with stock at a location; also the inactive ones when asked.
        $this->assertCount(2, $this->rows($export->xlsx(['location' => $location->id], 'nl')));
        $this->assertCount(4, $this->rows($export->xlsx(['active' => false], 'nl')));

        Livewire::test(StockQrExportPage::class)
            ->assertOk()
            ->assertSee('2 artikelen')
            ->assertSee(route('stock.qr', $cable))
            ->set('data.location', $location->id)
            ->assertSee('1 artikel')
            ->callAction('download')
            ->assertFileDownloaded(StockQrExport::filename());
    }

    #[Test]
    public function only_who_sees_the_stock_opens_the_export(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAs(User::factory()->withPermissions(['vehicles' => ['view']])->inTeam()->create());

        $this->assertFalse(StockQrExportPage::canAccess());
    }
}
