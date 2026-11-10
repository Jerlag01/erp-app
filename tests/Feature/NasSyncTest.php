<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\CertificateType;
use App\Enums\ProjectFileSection;
use App\Filament\Admin\Pages\Settings;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\FuelPass;
use App\Models\Incident;
use App\Models\NasSyncItem;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\TimesheetWeek;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkSite;
use App\Support\Nas\NasSync;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The copy of the files on the NAS (Synology File Station API, faked here).
 */
class NasSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{path: string, name: string, contents: string}> what reached the "NAS" */
    protected array $uploads = [];

    protected bool $offline = false;

    protected int $logins = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->withoutDefer();
        settings()->save('nas', ['enabled' => true, 'url' => 'https://nas.test:5001', 'username' => 'erp', 'password' => Crypt::encryptString('geheim'), 'root' => '/ERP', 'verify_ssl' => true]);

        Http::fake(['nas.test:5001/*' => function (Request $request) {
            if ($this->offline) {
                return Http::response('', 503);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $api = $query['api'] ?? $request['api'] ?? null;

            if ($api === 'SYNO.API.Auth' && ($query['method'] ?? $request['method']) === 'login') {
                $this->logins++;

                return Http::response(['success' => $request['passwd'] === 'geheim', 'data' => ['sid' => 'sid-'.$this->logins], 'error' => ['code' => 400]]);
            }

            if ($api === 'SYNO.FileStation.Upload') {
                $fields = collect($request->data())->keyBy('name');
                $this->uploads[] = ['path' => (string) $fields['path']['contents'], 'name' => (string) $fields['file']['filename'], 'contents' => (string) $fields['file']['contents']];

                return Http::response(['success' => true]);
            }

            if ($api === 'SYNO.FileStation.List') {
                return Http::response(['success' => true, 'data' => ['shares' => [['name' => 'ERP', 'path' => '/ERP']]]]);
            }

            return Http::response(['success' => true]);
        }]);
    }

    protected function uploaded(string $path): array
    {
        return collect($this->uploads)->first(fn (array $upload): bool => $path === $upload['path'].'/'.$upload['name']) ?? $this->fail("Not on the NAS: {$path}\nOn the NAS: ".collect($this->uploads)->map(fn ($u) => $u['path'].'/'.$u['name'])->implode("\n"));
    }

    #[Test]
    public function project_uploads_go_to_the_project_folder_per_upload_zone(): void
    {
        $project = Project::factory()->inCategory('604')->for(WorkSite::factory()->create(['cow_code' => '02GAM']))->create();
        ProjectFile::store($project, ProjectFileSection::Images, UploadedFile::fake()->createWithContent('kast.jpg', 'jpeg'), 'kast.jpg');
        ProjectFile::store($project, ProjectFileSection::WasteReports, UploadedFile::fake()->createWithContent('afval.pdf', '%PDF-afval'), 'afval.pdf');

        $this->assertSame('jpeg', $this->uploaded("/ERP/604/{$project->reference}/".ProjectFileSection::Images->getLabel().'/kast.jpg')['contents']);
        $this->assertSame('%PDF-afval', $this->uploaded("/ERP/604/{$project->reference}/".ProjectFileSection::WasteReports->getLabel().'/afval.pdf')['contents']);
        $this->assertSame(2, NasSyncItem::query()->where('status', NasSyncItem::DONE)->count());

        // Switched off: nothing is copied.
        settings()->save('nas', [...settings('nas'), 'enabled' => false]);
        ProjectFile::store($project, ProjectFileSection::Images, UploadedFile::fake()->create('later.jpg', 1), 'later.jpg');
        $this->assertCount(2, $this->uploads);
    }

    #[Test]
    public function an_employee_has_a_folder_with_documents_personal_details_prestations_and_certificates(): void
    {
        Storage::disk('local')->put('employees/driver-licenses/r.pdf', 'rijbewijs');
        Storage::disk('local')->put('employees/id-cards/id.jpg', 'id');
        $user = User::factory()->create();
        $employee = Employee::factory()->for($user)->create([
            'first_name' => 'Eva', 'last_name' => 'Peeters', 'national_number' => '90.01.01-123.45',
            'driver_license_files' => ['employees/driver-licenses/r.pdf'], 'driver_license_file_names' => ['employees/driver-licenses/r.pdf' => 'rijbewijs.pdf'],
            'id_card_files' => ['employees/id-cards/id.jpg'], 'id_card_file_names' => ['employees/id-cards/id.jpg' => 'identiteitskaart.jpg'],
        ]);
        Storage::disk('local')->put('employees/certificates/vca.pdf', 'vca');
        EmployeeCertificate::query()->create(['employee_id' => $employee->id, 'type' => CertificateType::cases()[0], 'obtained_on' => '2025-01-10', 'document' => 'employees/certificates/vca.pdf', 'document_name' => 'vca.pdf']);

        $folder = '/ERP/Personeel/Eva Peeters';
        $this->assertSame('rijbewijs', $this->uploaded("{$folder}/Rijbewijs/rijbewijs.pdf")['contents']);
        $this->assertSame('id', $this->uploaded("{$folder}/Identiteitskaart/identiteitskaart.jpg")['contents']);
        $this->assertStringStartsWith('%PDF', $this->uploaded("{$folder}/Eva Peeters - persoonsgegevens.pdf")['contents']);
        $this->assertNotEmpty(collect($this->uploads)->filter(fn (array $upload): bool => $upload['path'] === "{$folder}/Certificaten" && str_ends_with($upload['name'], 'vca.pdf')));

        // The weekly prestation PDF and a medical certificate of an absence.
        Storage::disk('local')->put('timesheets/w45.pdf', '%PDF-week');
        TimesheetWeek::query()->create(['user_id' => $user->id, 'week_start' => '2026-11-02'])->update(['pdf_path' => 'timesheets/w45.pdf']);
        $this->assertSame('%PDF-week', $this->uploaded("{$folder}/Prestaties/2026-W45.pdf")['contents']);

        Storage::disk('local')->put('absences/attest.pdf', '%PDF-attest');
        Absence::factory()->for($user)->create(['type' => AbsenceType::Medical, 'start_date' => '2026-11-03', 'end_date' => '2026-11-04', 'document' => 'absences/attest.pdf', 'document_name' => 'attest.pdf']);
        $this->assertSame('%PDF-attest', $this->uploaded("{$folder}/Medische attesten/2026-11-03 attest.pdf")['contents']);

        // The personal details PDF holds them.
        $html = view('nas.employee-pdf', ['employee' => $employee->fresh(['certificates', 'medicalChecks', 'user', 'country'])])->render();
        $this->assertStringContainsString('90010112345', $html);
    }

    #[Test]
    public function vehicles_incidents_and_toolbox_meetings_get_their_pdfs(): void
    {
        $vehicle = Vehicle::factory()->create(['plate_number' => '1ABC123']);
        FuelPass::factory()->for($vehicle)->create(['card_number' => '700212345678', 'pin_code' => '4821']);
        $this->assertStringStartsWith('%PDF', $this->uploaded('/ERP/Wagenpark/1ABC123/1ABC123 - fiche.pdf')['contents']);
        $html = view('nas.vehicle-pdf', ['vehicle' => $vehicle->fresh(['fuelPasses', 'driver'])])->render();
        $this->assertStringContainsString('7002 1234 5678', $html);
        $this->assertStringNotContainsString('4821', $html, 'Never a PIN code.');

        $incident = Incident::factory()->create(['reporter_name' => 'Jan Peeters', 'reported_at' => '2026-11-05 10:00']);
        $this->assertStringStartsWith('%PDF', $this->uploaded("/ERP/Incidenten/2026-11-05 - incident {$incident->id} - Jan Peeters.pdf")['contents']);

        Storage::disk('local')->put('toolbox/ladders.pdf', '%PDF-bijlage');
        ToolboxMeeting::factory()->create(['title' => 'Werken op hoogte', 'meeting_month' => '2026-11-01', 'attachments' => ['toolbox/ladders.pdf'], 'attachment_names' => ['toolbox/ladders.pdf' => 'ladders.pdf']]);
        $folder = '/ERP/Toolboxen/2026-11 Werken op hoogte';
        $this->assertStringStartsWith('%PDF', $this->uploaded("{$folder}/Aanwezigheidslijst.pdf")['contents']);
        $this->assertStringStartsWith('%PDF', $this->uploaded("{$folder}/Inhoud.pdf")['contents']);
        $this->assertSame('%PDF-bijlage', $this->uploaded("{$folder}/Bijlagen/ladders.pdf")['contents']);
    }

    #[Test]
    public function what_fails_while_the_nas_is_offline_is_sent_again_and_everything_can_be_copied_once(): void
    {
        $project = Project::factory()->inCategory('610')->create();
        $this->offline = true;
        ProjectFile::store($project, ProjectFileSection::Documents, UploadedFile::fake()->createWithContent('offerte.pdf', 'x'), 'offerte.pdf');

        $item = NasSyncItem::query()->sole();
        $this->assertSame([NasSyncItem::FAILED, 1], [$item->status, $item->attempts]);
        $this->assertCount(0, $this->uploads);

        // Back online: the scheduled command sends it.
        $this->offline = false;
        $this->artisan('nas:sync')->assertSuccessful();
        $this->assertSame(NasSyncItem::DONE, $item->fresh()->status);
        $this->assertCount(1, $this->uploads);

        // "Everything" puts every file in the outbox again (each place once) and sends it.
        $this->uploads = [];
        $this->artisan('nas:sync', ['--all' => true])->assertSuccessful();
        $this->assertTrue(collect($this->uploads)->contains(fn (array $upload): bool => $upload['name'] === 'offerte.pdf'));
        $this->assertSame(NasSyncItem::query()->count(), NasSyncItem::query()->distinct()->count('target_hash'));
    }

    #[Test]
    public function names_are_valid_on_the_nas_and_a_wrong_password_is_reported(): void
    {
        $this->assertSame('Fiche - A-B', NasSync::clean('Fiche / A:B'));
        $this->assertSame('-', NasSync::clean('..'));

        settings()->save('nas', [...settings('nas'), 'password' => Crypt::encryptString('fout')]);
        Vehicle::factory()->create();
        $item = NasSyncItem::query()->sole();
        $this->assertSame(NasSyncItem::FAILED, $item->status);
        $this->assertStringContainsString('400', (string) $item->last_error);
    }

    #[Test]
    public function the_administrator_sets_up_and_tests_the_nas_in_the_settings(): void
    {
        settings()->save('nas', [...settings('nas'), 'password' => null]);
        Filament::setCurrentPanel('admin');
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Settings::class)
            ->set('data.nas.password', 'geheim')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('geheim', Crypt::decryptString(settings()->get('nas.password')));
        $this->assertSame('/ERP', settings()->get('nas.root'));

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Settings::class)
            ->callAction(TestAction::make('testNas')->schemaComponent('nasActions', schema: 'form'))
            ->assertNotified(__('erp.nas.test_ok'));
    }
}
