<?php

namespace Tests\Feature;

use App\Enums\BuildingType;
use App\Filament\Resources\WorkSites\Pages\CreateWorkSite;
use App\Filament\Resources\WorkSites\Pages\ListWorkSites;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\WorkSiteImport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkSiteImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * An .xlsx like the Proximus "Sites_COW" list.
     *
     * @param  list<list<mixed>>  $rows
     */
    protected function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sites').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    protected function sitesFile(): string
    {
        return $this->xlsx([
            ['Cow code', 'Area', 'Type of site', 'Street', 'Zip code', 'City', 'Lokalen', 'Responsible mail'],
            ['02MAR', 'C1', 'RT.LDC', 'Broekstraat, 72-74', 1000, 'BRUSSEL / BRUXELLES', 'A00A007, B01', 'x@example.com'],
            ['02e23', 'C', 'RT.Technical', 'Joseph II straat 54', 1000, 'BRUSSEL / BRUXELLES', '', ''],
            ['55MUN', '', 'Colibri', 'Kerkstraat 1', 9630, 'Zwalm', 'K1', ''],
            ['2X', '', '', 'Nergensstraat 1', 1000, 'Nergens', '', ''],
            ['', '', '', '', '', '', '', ''],
        ]);
    }

    #[Test]
    public function cow_codes_may_end_in_letters_or_digits(): void
    {
        $this->assertMatchesRegularExpression(WorkSite::COW_CODE_PATTERN, '02E23');
        $this->assertMatchesRegularExpression(WorkSite::COW_CODE_PATTERN, '02GAM');
        $this->assertMatchesRegularExpression(WorkSite::COW_CODE_PATTERN, '41LB1');
        $this->assertDoesNotMatchRegularExpression(WorkSite::COW_CODE_PATTERN, '2EAB1');
        $this->assertDoesNotMatchRegularExpression(WorkSite::COW_CODE_PATTERN, '02GA');
        $this->assertDoesNotMatchRegularExpression(WorkSite::COW_CODE_PATTERN, '02GA-1');

        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '02e23', 'building_type' => 'atmk'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('02E23', WorkSite::sole()->cow_code);
    }

    #[Test]
    public function ec_ldc_and_colibri_sites_have_rooms(): void
    {
        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '02LDC', 'building_type' => 'ldc'])
            ->assertFormFieldVisible('rooms')
            ->fillForm(['rooms' => ['A00A007', 'B01']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['A00A007', 'B01'], WorkSite::query()->where('cow_code', '02LDC')->sole()->rooms);

        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '02ATM', 'building_type' => 'atmk'])
            ->assertFormFieldHidden('rooms');

        $this->get('/work-sites/'.WorkSite::query()->where('cow_code', '02LDC')->value('id'))->assertOk()->assertSee('A00A007');
    }

    #[Test]
    public function the_import_follows_the_chosen_columns(): void
    {
        $path = $this->sitesFile();
        $import = app(WorkSiteImport::class);

        $headers = $import->headers($path);
        $this->assertSame('Cow code', $headers[0]);
        $this->assertSame(['cow_code', 'area', 'building_type', 'street_with_number', 'postal_code', 'city', 'rooms', null], array_map(fn (string $header): ?string => WorkSiteImport::guess($header), $headers));

        WorkSite::factory()->create(['cow_code' => '55MUN', 'building_type' => BuildingType::Atmk, 'city' => 'Oud']);

        $result = $import->import($path, [0 => 'cow_code', 1 => 'area', 2 => 'building_type', 3 => 'street_with_number', 4 => 'postal_code', 5 => 'city', 6 => 'rooms', 7 => null], BuildingType::Atmk, updateExisting: false);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('"2X"', $result['skipped'][0]);

        $mar = WorkSite::query()->where('cow_code', '02MAR')->sole();
        $this->assertSame([BuildingType::Ldc, 'Broekstraat', '72-74', '1000', 'BRUSSEL / BRUXELLES', 'C1', ['A00A007', 'B01']], [$mar->building_type, $mar->street, $mar->house_number, $mar->postal_code, $mar->city, $mar->area?->name, $mar->rooms]);

        $e23 = WorkSite::query()->where('cow_code', '02E23')->sole();
        $this->assertSame(BuildingType::Atmk, $e23->building_type, 'Unknown types get the default.');
        $this->assertSame(['Joseph II straat', '54'], [$e23->street, $e23->house_number]);
        $this->assertSame('Oud', WorkSite::query()->where('cow_code', '55MUN')->value('city'));

        // Again, updating existing sites: only filled-in values, never the building type.
        $again = $import->import($path, [0 => 'cow_code', 5 => 'city', 6 => 'rooms'], BuildingType::Atmk, updateExisting: true);
        $this->assertSame(0, $again['created']);
        $mun = WorkSite::query()->where('cow_code', '55MUN')->sole();
        $this->assertSame(['Zwalm', ['K1'], BuildingType::Atmk], [$mun->city, $mun->rooms, $mun->building_type]);

        $this->assertSame(BuildingType::Ec, WorkSiteImport::buildingType('RT.EC'));
        $this->assertNull(WorkSiteImport::buildingType('RT.Technical'));
        $this->assertSame(BuildingType::Unknown, WorkSiteImport::buildingType('???'));
        $this->assertSame(BuildingType::Atropos, WorkSiteImport::buildingType('RT.Atropos'));
        $this->assertSame(BuildingType::Contropos, WorkSiteImport::buildingType('Contropos'));
        $this->assertSame(BuildingType::Unknown, WorkSiteImport::buildingType('Onbekend'));
    }

    #[Test]
    public function the_import_runs_from_the_work_site_list(): void
    {
        $file = UploadedFile::fake()->createWithContent('sites.xlsx', (string) file_get_contents($this->sitesFile()));

        Livewire::test(ListWorkSites::class)
            ->callAction('import', [
                'file' => $file,
                'mapping' => [0 => 'cow_code', 2 => 'building_type', 3 => 'street_with_number', 4 => 'postal_code', 5 => 'city', 6 => 'rooms'],
                'default_type' => 'ec',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['02E23', '02MAR', '55MUN'], WorkSite::query()->orderBy('cow_code')->pluck('cow_code')->all());
        $this->assertSame(BuildingType::Ec, WorkSite::query()->where('cow_code', '02E23')->value('building_type'));

        $this->actingAs(User::factory()->withPermissions(['work_sites' => ['view']])->inTeam()->create());
        Livewire::test(ListWorkSites::class)->assertActionHidden('import');
    }

    #[Test]
    public function a_site_can_have_an_unknown_building_type(): void
    {
        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '02ONB', 'building_type' => 'unknown'])
            ->call('create')
            ->assertHasNoFormErrors();

        $site = WorkSite::query()->where('cow_code', '02ONB')->sole();
        $this->assertSame(BuildingType::Unknown, $site->building_type);
        $this->assertFalse(WorkSite::hasRooms($site->building_type));

        Livewire::test(ListWorkSites::class)->mountAction('import')->assertActionDataSet(['default_type' => BuildingType::Unknown]);
    }
}
