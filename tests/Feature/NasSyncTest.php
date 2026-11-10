<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\CertificateType;
use App\Enums\ProjectFileSection;
use App\Filament\Admin\Resources\NasTargets\NasTargetResource;
use App\Filament\Admin\Resources\NasTargets\Pages\ManageNasTargets;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\FuelPass;
use App\Models\Incident;
use App\Models\NasSyncItem;
use App\Models\NasTarget;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\TimesheetWeek;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkSite;
use App\Support\Nas\FlysystemTransport;
use App\Support\Nas\NasSync;
use App\Support\Nas\NasTransport;
use App\Support\Nas\NasTransports;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The copy of the files on the NAS (Synology File Station API, faked here).
 */
class NasSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{host: string, path: string, name: string, contents: string}> what reached the "NAS" systems */
    protected array $uploads = [];

    /** @var list<string> hosts that are down */
    protected array $offline = [];

    protected int $logins = 0;

    protected NasTarget $nas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->withoutDefer();
        $this->nas = NasTarget::query()->create(['name' => 'NAS kantoor', 'driver' => NasTransports::SYNOLOGY, 'url' => 'https://nas.test:5001', 'username' => 'erp', 'password' => 'geheim', 'root' => '/ERP']);

        Http::fake(['*.test:5001/*' => function (Request $request) {
            $host = (string) parse_url($request->url(), PHP_URL_HOST);

            if (in_array($host, $this->offline, true)) {
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
                $this->uploads[] = ['host' => $host, 'path' => (string) $fields['path']['contents'], 'name' => (string) $fields['file']['filename'], 'contents' => (string) $fields['file']['contents']];

                return Http::response(['success' => true]);
            }

            if ($api === 'SYNO.FileStation.List') {
                return Http::response(['success' => true, 'data' => ['shares' => [['name' => 'ERP', 'path' => '/ERP']]]]);
            }

            return Http::response(['success' => true]);
        }]);
    }

    protected function uploaded(string $path, string $host = 'nas.test'): array
    {
        return collect($this->uploads)->first(fn (array $upload): bool => $upload['host'] === $host && $path === $upload['path'].'/'.$upload['name']) ?? $this->fail("Not on the NAS: {$path}\nOn the NAS: ".collect($this->uploads)->map(fn ($u) => $u['path'].'/'.$u['name'])->implode("\n"));
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
        $this->nas->update(['is_active' => false]);
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
        $this->offline = ['nas.test'];
        ProjectFile::store($project, ProjectFileSection::Documents, UploadedFile::fake()->createWithContent('offerte.pdf', 'x'), 'offerte.pdf');

        $item = NasSyncItem::query()->sole();
        $this->assertSame([NasSyncItem::FAILED, 1], [$item->status, $item->attempts]);
        $this->assertCount(0, $this->uploads);

        // Back online: the scheduled command sends it.
        $this->offline = [];
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

        $this->nas->update(['password' => 'fout']);
        Vehicle::factory()->create();
        $item = NasSyncItem::query()->sole();
        $this->assertSame(NasSyncItem::FAILED, $item->status);
        $this->assertStringContainsString('400', (string) $item->last_error);
    }

    #[Test]
    public function the_administrator_manages_several_nas_systems_and_each_gets_every_file(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageNasTargets::class)
            ->callAction('create', ['name' => 'NAS backup', 'driver' => NasTransports::SYNOLOGY, 'url' => 'https://backup.test:5001/', 'username' => 'erp', 'password' => 'geheim', 'root' => 'ERP-backup', 'is_active' => true, 'verify_ssl' => true])
            ->assertHasNoActionErrors();
        $backup = NasTarget::query()->where('name', 'NAS backup')->sole();
        $this->assertSame(['https://backup.test:5001', '/ERP-backup', 'geheim'], [$backup->url, $backup->root, $backup->password]);
        $this->assertNotSame('geheim', DB::table('nas_targets')->where('id', $backup->id)->value('password'), 'Stored encrypted.');

        // Editing without a password keeps the one there is.
        Livewire::actingAs($admin)->test(ManageNasTargets::class)->callTableAction('edit', $backup, ['name' => 'NAS backup 2', 'password' => '']);
        $this->assertSame(['NAS backup 2', 'geheim'], [$backup->fresh()->name, $backup->fresh()->password]);

        Livewire::actingAs($admin)->test(ManageNasTargets::class)->callTableAction('test', $backup)->assertNotified(__('erp.nas.test_ok'));

        // A new file goes to both, each below its own main folder; one NAS being down does not stop the other.
        $this->offline = ['backup.test'];
        Vehicle::factory()->create(['plate_number' => '1ABC123']);
        $this->assertStringStartsWith('%PDF', $this->uploaded('/ERP/Wagenpark/1ABC123/1ABC123 - fiche.pdf')['contents']);
        $this->assertSame([NasSyncItem::DONE, NasSyncItem::FAILED], [
            $this->nas->items()->sole()->status,
            $backup->items()->sole()->status,
        ]);

        // Back up: the command sends what this NAS still misses.
        $this->offline = [];
        $this->artisan('nas:sync', ['--target' => $backup->id])->assertSuccessful();
        $this->assertStringStartsWith('%PDF', $this->uploaded('/ERP-backup/Wagenpark/1ABC123/1ABC123 - fiche.pdf', 'backup.test')['contents']);

        // "Copy everything" for one (new) NAS only fills its queue.
        $third = NasTarget::query()->create(['name' => 'NAS 3', 'driver' => NasTransports::SYNOLOGY, 'url' => 'https://third.test:5001', 'username' => 'erp', 'password' => 'geheim']);
        $before = $this->nas->items()->count();
        Livewire::actingAs($admin)->test(ManageNasTargets::class)->callTableAction('syncAll', $third);
        $this->assertSame(1, $third->items()->count());
        $this->assertSame($before, $this->nas->items()->count());

        // Only administrators see the list.
        $this->actingAs(User::factory()->create())->get(NasTargetResource::getUrl())->assertForbidden();
    }

    #[Test]
    public function ftp_and_sftp_write_below_the_main_folder_through_flysystem(): void
    {
        // The FTP and SFTP transports are Flysystem; a local folder stands in for the server here.
        $server = sys_get_temp_dir().'/nas-test-'.uniqid();
        $this->app->instance(NasTransport::class, new FlysystemTransport(new Filesystem(new LocalFilesystemAdapter($server)), 'test'));
        $this->nas->update(['driver' => NasTransports::SFTP, 'host' => 'nas.test', 'root' => '/volume1/ERP']);

        $project = Project::factory()->inCategory('604')->for(WorkSite::factory()->create(['cow_code' => '02GAM']))->create();
        ProjectFile::store($project, ProjectFileSection::Plans, UploadedFile::fake()->createWithContent('grondplan.dwg', 'dwg'), 'grondplan.dwg');

        $this->assertSame('dwg', file_get_contents("{$server}/volume1/ERP/604/{$project->reference}/".ProjectFileSection::Plans->getLabel().'/grondplan.dwg'));
        $this->assertSame(NasSyncItem::DONE, NasSyncItem::query()->sole()->status);
        File::deleteDirectory($server);

        // The real transports are built from the NAS record (they connect only when used).
        $this->assertInstanceOf(FlysystemTransport::class, NasTransports::for($this->nas->fresh()));
        $this->nas->update(['driver' => NasTransports::FTP]);
        $this->assertInstanceOf(FlysystemTransport::class, NasTransports::for($this->nas->fresh()));
    }

    #[Test]
    public function smb_uses_smbclient_with_a_credentials_file_and_reports_errors(): void
    {
        $this->nas->update(['driver' => NasTransports::SMB, 'host' => 'nas.test', 'share' => 'Data', 'domain' => 'PI', 'root' => '/ERP']);
        $credentials = null;
        Process::fake(function ($process) use (&$credentials) {
            $command = $process->command;
            $file = substr((string) collect($command)->first(fn ($part) => str_starts_with((string) $part, '--authentication-file=')), strlen('--authentication-file='));
            $credentials = is_file($file) ? file_get_contents($file) : null;

            return Process::result(output: "putting file\n");
        });

        Vehicle::factory()->create(['plate_number' => '1ABC123']);

        Process::assertRan(function ($process): bool {
            $command = $process->command;
            $script = (string) $command[array_search('-c', $command, true) + 1];

            return $command[0] === 'smbclient' && $command[1] === '//nas.test/Data'
                && ! str_contains(implode(' ', $command), 'geheim')
                && str_contains($script, 'mkdir "ERP"; mkdir "ERP\\Wagenpark"; mkdir "ERP\\Wagenpark\\1ABC123"')
                && str_contains($script, 'cd "\\ERP\\Wagenpark\\1ABC123"')
                && str_contains($script, '"1ABC123 - fiche.pdf"');
        });
        $this->assertStringContainsString('password = geheim', (string) $credentials);
        $this->assertStringContainsString('domain = PI', (string) $credentials);
        $this->assertSame(NasSyncItem::DONE, NasSyncItem::query()->sole()->status);

        // "Already exists" is fine; any other error fails the file.
        Process::fake(fn () => Process::result(output: "NT_STATUS_ACCESS_DENIED making remote directory \\ERP\n", exitCode: 1));
        Vehicle::factory()->create(['plate_number' => '2DEF456']);
        $failed = NasSyncItem::query()->where('target', 'like', '%2DEF456%')->sole();
        $this->assertSame(NasSyncItem::FAILED, $failed->status);
        $this->assertStringContainsString('NT_STATUS_ACCESS_DENIED', (string) $failed->last_error);
    }
}
