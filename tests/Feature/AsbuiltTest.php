<?php

namespace Tests\Feature;

use App\Enums\AsbuiltCategory;
use App\Enums\AsbuiltStatus;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\Asbuilts\Pages\ListClientAsbuilts;
use App\Filament\Client\Resources\Asbuilts\Pages\ViewClientAsbuilt;
use App\Filament\Resources\AsbuiltLibraryFiles\Pages\ManageAsbuiltLibraryFiles;
use App\Filament\Resources\Asbuilts\AsbuiltResource;
use App\Filament\Resources\Asbuilts\Pages\ViewAsbuilt;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Mail\SystemMail;
use App\Models\Asbuilt;
use App\Models\AsbuiltLibraryFile;
use App\Models\BatteryDossier;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteContact;
use App\Support\Asbuilts;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class AsbuiltTest extends TestCase
{
    use RefreshDatabase;

    protected WorkSite $site;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Filament::setCurrentPanel('app');
        $this->site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $this->team = Team::factory()->create();
    }

    protected function library(AsbuiltCategory $category, ?WorkSite $site, string $name): AsbuiltLibraryFile
    {
        Storage::disk('local')->put("asbuilt-library/{$name}", "contents of {$name}");

        return AsbuiltLibraryFile::query()->create(['category' => $category, 'work_site_id' => $site?->id, 'title' => $name, 'path' => "asbuilt-library/{$name}", 'original_name' => $name, 'size' => 20]);
    }

    protected function executedProject(string $category = '610'): Project
    {
        $project = Project::factory()->inCategory($category)->for($this->site)->create(['status' => ProjectStatus::InProgress]);
        $project->teams()->attach($this->team);
        $project->update(['status' => ProjectStatus::Executed]);

        return $project->refresh();
    }

    protected function manager(): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'asbuilt' => ['view', 'manage', 'library']])->inTeam($this->team)->create();
    }

    #[Test]
    public function an_executed_project_gets_an_asbuilt_with_the_standard_files_of_its_cow_code(): void
    {
        $evacuation = $this->library(AsbuiltCategory::EvacuationRoutes, $this->site, 'evacuatie-02GAM.pdf');
        $otherSite = $this->library(AsbuiltCategory::EvacuationRoutes, WorkSite::factory()->create(), 'evacuatie-ander.pdf');
        $external = $this->library(AsbuiltCategory::ExternalInfluences, WorkSite::factory()->create(), 'extern.pdf');
        $general = $this->library(AsbuiltCategory::CableDimensions, null, 'kabeltabel.xlsx');
        $technical = $this->library(AsbuiltCategory::TechnicalDocuments, null, 'datasheet.pdf');

        $project = $this->executedProject();
        $asbuilt = $project->asbuilt;

        $this->assertSame(AsbuiltStatus::InProgress, $asbuilt->status);
        // External influences are global: never tied to a COW-code, in every as-built.
        $this->assertNull($external->fresh()->work_site_id);
        $this->assertEqualsCanonicalizing([$evacuation->id, $general->id, $external->id], $asbuilt->files()->pluck('asbuilt_library_file_id')->all());
        $this->assertNotContains($otherSite->id, $asbuilt->files()->pluck('asbuilt_library_file_id')->all());
        $this->assertNotContains($technical->id, $asbuilt->files()->pluck('asbuilt_library_file_id')->all(), 'Technical documents are picked by hand.');

        // Not for the excluded categories, and only once.
        $this->assertNull($this->executedProject('604')->asbuilt);
        $this->assertNull($this->executedProject('608.1')->asbuilt);
        $project->update(['status' => ProjectStatus::InvoicingToDo]);
        $project->update(['status' => ProjectStatus::Executed]);
        $this->assertSame(1, Asbuilt::query()->where('project_id', $project->id)->count());
    }

    #[Test]
    public function the_asbuilt_is_made_when_the_project_is_to_be_executed(): void
    {
        $project = Project::factory()->inCategory('610')->for($this->site)->create(['status' => ProjectStatus::Planned]);
        $this->assertNull($project->asbuilt, 'Not yet while it is planned.');

        $project->update(['status' => ProjectStatus::ToBeExecuted]);
        $this->assertNotNull($project->fresh()->asbuilt);
        $this->assertSame(Asbuilt::ORIGIN_AUTOMATIC, $project->fresh()->asbuilt->origin);

        // Made straight away at "to be executed" too.
        $this->assertNotNull(Project::factory()->inCategory('620')->for($this->site)->create(['status' => ProjectStatus::ToBeExecuted])->asbuilt);
        $this->assertNull(Project::factory()->inCategory('620')->for($this->site)->create(['status' => ProjectStatus::Ordered])->asbuilt);
    }

    #[Test]
    public function files_are_added_per_category_and_the_btv_report_closes_it_into_a_zip_for_the_client(): void
    {
        $technical = $this->library(AsbuiltCategory::TechnicalDocuments, null, 'datasheet.pdf');
        $project = $this->executedProject();
        $asbuilt = $project->asbuilt;
        $contact = WorkSiteContact::factory()->create();
        $this->site->contacts()->attach($contact);
        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS, ClientPortal::ASBUILT])->create(['email' => 'pm@proximus.example', 'locale' => 'fr', 'work_site_contact_id' => $contact->id]);
        $this->actingAs($this->manager());

        $page = fn () => Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()]);

        // Any format, per category; evacuation routes are kept with the COW-code for later as-builts.
        $page()->callAction('upload', ['category' => AsbuiltCategory::Plans->value, 'files' => [UploadedFile::fake()->create('grondplan.dwg', 30)]])->assertHasNoActionErrors();
        $page()->callAction('upload', ['category' => AsbuiltCategory::EvacuationRoutes->value, 'files' => [UploadedFile::fake()->create('evacuatie.pdf', 30)]])->assertHasNoActionErrors();
        $page()->callAction('technical', ['library' => [$technical->id], 'files' => [UploadedFile::fake()->create('nieuwe-fiche.docx', 10)]])->assertHasNoActionErrors();

        $this->assertSame(4, $asbuilt->files()->count());
        $this->assertTrue(AsbuiltLibraryFile::query()->where('category', 'evacuation_routes')->where('work_site_id', $this->site->id)->exists());
        $this->assertSame(2, AsbuiltLibraryFile::query()->where('category', 'technical_documents')->count(), 'New technical documents join the register.');

        // The BTV report needs a planned inspection first.
        $page()->assertActionHidden('btvReport');
        $page()->callAction('planBtv', ['btv_date' => '2026-12-01'])->assertHasNoActionErrors();
        $this->assertSame(AsbuiltStatus::BtvPlanned, $asbuilt->fresh()->status);

        $page()->callAction('btvReport', ['files' => [UploadedFile::fake()->create('keuring.pdf', 40)]])->assertHasNoActionErrors();

        $asbuilt->refresh();
        $this->assertSame(AsbuiltStatus::Closed, $asbuilt->status);
        $this->assertSame($project->reference.' - ASBUILT.zip', $asbuilt->zipName());

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($asbuilt->zip_path));
        $names = array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $zip->close();
        $this->assertContains('4 - plans/grondplan.dwg', $names);
        $this->assertContains('8 - evacuation routes/evacuatie.pdf', $names);
        $this->assertContains('6 - technical documents/datasheet.pdf', $names);
        $this->assertContains('0 - BTV control/keuring.pdf', $names);
        $this->assertContains('2 - ICC calculations/', $names);

        // The client of that COW-code is told, in French, and downloads the ZIP.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('pm@proximus.example') && $mail->key === MailTemplateKey::AsbuiltAvailable && str_contains($mail->rendered['subject'], 'disponible'));
        $this->actingAs($client, ClientPortal::GUARD);
        $this->get(route('client.asbuilt.zip', $asbuilt))->assertOk()->assertDownload($project->reference.' - ASBUILT.zip');

        // Closed: no more uploads, until it is reopened.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view'], 'asbuilt' => ['view', 'manage', 'reopen']])->inTeam($this->team)->create());
        $page()->assertActionHidden('upload')->callAction('reopen');
        $this->assertSame(AsbuiltStatus::BtvPlanned, $asbuilt->fresh()->status);
        $this->assertNull($asbuilt->fresh()->zip_path);
    }

    #[Test]
    public function the_employees_on_the_project_and_the_client_have_their_own_access(): void
    {
        $project = $this->executedProject();
        $asbuilt = $project->asbuilt;
        $leader = User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create();
        $project->leaders()->attach($leader);

        // On the project: open and download, without the as-built permissions; but not change it.
        $this->actingAs($leader);
        $this->get(AsbuiltResource::getUrl('view', ['record' => $asbuilt]))->assertOk();
        Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()])->assertActionHidden('upload');
        $this->get('/asbuilts')->assertForbidden();

        // Someone else: nothing.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create());
        $this->get(AsbuiltResource::getUrl('view', ['record' => $asbuilt]))->assertForbidden();

        // The client of the project hands in a ZIP in the general zone and plans per category; the leader is told.
        $contact = WorkSiteContact::factory()->create();
        $this->site->contacts()->attach($contact);
        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS, ClientPortal::ASBUILT])->create(['work_site_contact_id' => $contact->id, 'name' => 'Proximus PM']);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);

        Livewire::test(ListClientAsbuilts::class)->assertCanSeeTableRecords([$asbuilt]);
        Livewire::test(ViewClientAsbuilt::class, ['record' => $asbuilt->getRouteKey()])
            ->callAction('upload', ['category' => AsbuiltCategory::GENERAL, 'files' => [UploadedFile::fake()->create('alles.zip', 50)]])
            ->assertHasNoActionErrors()
            ->callAction('upload', ['category' => AsbuiltCategory::IccCalculations->value, 'files' => [UploadedFile::fake()->create('icc.xlsx', 5)]])
            ->assertHasNoActionErrors()
            ->assertSee('alles.zip');

        $this->assertSame(2, $asbuilt->files()->whereNotNull('uploaded_by_client_user_id')->count());
        $this->assertSame(2, $leader->notifications()->count());

        // The client also sees and downloads what the employees put in, while the as-built is still open.
        Storage::disk('local')->put('asbuilt/'.$asbuilt->id.'/meetrapport.pdf', 'pdf');
        [$employeeFile] = app(Asbuilts::class)->addFiles($asbuilt, AsbuiltCategory::BtvReports->value, ['asbuilt/'.$asbuilt->id.'/meetrapport.pdf'], [], $leader);
        $this->assertFalse($asbuilt->fresh()->isClosed());
        Livewire::test(ViewClientAsbuilt::class, ['record' => $asbuilt->getRouteKey()])->assertSee('meetrapport.pdf')->assertSee('icc.xlsx');
        $this->get(route('client.asbuilt.file', $employeeFile))->assertOk()->assertDownload('meetrapport.pdf');

        // Categories the client may not hand in are refused.
        Livewire::test(ViewClientAsbuilt::class, ['record' => $asbuilt->getRouteKey()])
            ->callAction('upload', ['category' => AsbuiltCategory::BtvReports->value, 'files' => [UploadedFile::fake()->create('nep.pdf', 5)]])
            ->assertHasActionErrors(['category']);

        // Without the as-built permission, or for another client's project, nothing.
        $client->update(['permissions' => [ClientPortal::PROJECTS]]);
        $this->actingAs($client->fresh(), ClientPortal::GUARD);
        $this->get('/client/asbuilts')->assertForbidden();
    }

    #[Test]
    public function standard_files_are_uploaded_into_the_register_and_reach_the_open_asbuilts(): void
    {
        $asbuilt = $this->executedProject()->asbuilt;
        $elsewhere = Project::factory()->inCategory('630')->for(WorkSite::factory()->create())->create();
        $elsewhere->update(['status' => ProjectStatus::Executed]);
        $this->actingAs($this->manager());

        // Evacuation routes need a COW-code.
        Livewire::test(ManageAsbuiltLibraryFiles::class)
            ->callAction('upload', ['category' => AsbuiltCategory::EvacuationRoutes->value, 'work_site_id' => null, 'files' => [UploadedFile::fake()->create('evacuatie.pdf', 5)]])
            ->assertHasActionErrors(['work_site_id' => 'required']);

        Livewire::test(ManageAsbuiltLibraryFiles::class)
            ->callAction('upload', ['category' => AsbuiltCategory::EvacuationRoutes->value, 'work_site_id' => $this->site->id, 'files' => [UploadedFile::fake()->create('evacuatie.pdf', 5), UploadedFile::fake()->create('foto.heic', 5)]])
            ->assertHasNoActionErrors();

        $this->assertSame(2, AsbuiltLibraryFile::query()->where('work_site_id', $this->site->id)->count());
        $this->assertSame(2, $asbuilt->files()->where('category', 'evacuation_routes')->count());
        $this->assertSame(0, $elsewhere->asbuilt->files()->where('category', 'evacuation_routes')->count());

        // External influences are global: no COW-code, and they reach every open as-built.
        Livewire::test(ManageAsbuiltLibraryFiles::class)
            ->callAction('upload', ['category' => AsbuiltCategory::ExternalInfluences->value, 'work_site_id' => $this->site->id, 'files' => [UploadedFile::fake()->create('extern.pdf', 5)]])
            ->assertHasNoActionErrors();

        $this->assertNull(AsbuiltLibraryFile::query()->where('category', 'external_influences')->value('work_site_id'));
        $this->assertSame(1, $asbuilt->files()->where('category', 'external_influences')->count());
        $this->assertSame(1, $elsewhere->asbuilt->files()->where('category', 'external_influences')->count());

        // One uploaded inside an as-built goes to the other open as-builts too.
        Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()])
            ->callAction('upload', ['category' => AsbuiltCategory::ExternalInfluences->value, 'files' => [UploadedFile::fake()->create('trillingen.pdf', 5)]])
            ->assertHasNoActionErrors();
        $this->assertSame(2, $elsewhere->asbuilt->files()->where('category', 'external_influences')->count());
    }

    #[Test]
    public function an_asbuilt_made_with_the_button_is_not_made_again_when_the_project_is_executed(): void
    {
        $evacuation = $this->library(AsbuiltCategory::EvacuationRoutes, $this->site, 'evacuatie.pdf');
        $project = Project::factory()->inCategory('620')->for($this->site)->create(['status' => ProjectStatus::Planned]);
        $project->teams()->attach($this->team);
        $manager = $this->manager();

        // Without "asbuilt.manage" there is no button.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('createAsbuilt');

        $this->actingAs($manager);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('createAsbuilt')
            ->assertRedirect(AsbuiltResource::getUrl('view', ['record' => $project->fresh()->asbuilt]));

        $asbuilt = $project->fresh()->asbuilt;
        $this->assertTrue($asbuilt->wasCreatedManually());
        $this->assertTrue($asbuilt->creator->is($manager));
        $this->assertSame([$evacuation->id], $asbuilt->files()->pluck('asbuilt_library_file_id')->all());

        // The button is gone, and "to be executed" (where it is made automatically) keeps that one as-built.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('createAsbuilt')->assertActionVisible('asbuilt');
        $project->refresh()->update(['status' => ProjectStatus::ToBeExecuted]);
        $project->refresh()->update(['status' => ProjectStatus::Executed]);
        $this->assertSame(1, Asbuilt::query()->where('project_id', $project->id)->count());
        $this->assertSame($asbuilt->id, $project->fresh()->asbuilt->id);
        $this->assertSame(Asbuilt::ORIGIN_MANUAL, $asbuilt->fresh()->origin);

        // Automatically made ones are marked as such; excluded categories get no button.
        $this->assertSame(Asbuilt::ORIGIN_AUTOMATIC, $this->executedProject()->asbuilt->origin);
        $excluded = Project::factory()->inCategory('604')->for($this->site)->create();
        $excluded->teams()->attach($this->team);
        Livewire::test(ViewProject::class, ['record' => $excluded->getRouteKey()])->assertActionHidden('createAsbuilt');
    }

    #[Test]
    public function asbuilts_are_removed_on_the_admin_side_only_with_the_explicit_permission(): void
    {
        $asbuilt = $this->executedProject()->asbuilt;
        Storage::disk('local')->put('asbuilts/own.pdf', 'own');
        $own = $asbuilt->files()->create(['category' => AsbuiltCategory::Plans, 'path' => 'asbuilts/own.pdf', 'original_name' => 'own.pdf', 'size' => 3]);

        // An administrator without the ticked permission cannot remove it.
        $admin = User::factory()->admin()->create();
        $this->assertFalse($admin->can('delete', $asbuilt));

        $allowed = User::factory()->create(['role_id' => Role::factory()->create(['is_admin' => true, 'permissions' => ['asbuilt' => ['view', 'delete']]])->id]);
        $this->actingAs($allowed);

        // The employees' side never offers it.
        Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()])->assertActionHidden('delete');

        Filament::setCurrentPanel('admin');
        Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()])
            ->assertActionVisible('delete')
            ->callAction('delete');

        $this->assertModelMissing($asbuilt);
        $this->assertModelMissing($own);
        Storage::disk('local')->assertMissing('asbuilts/own.pdf');

        $this->actingAs($admin);
        $other = $this->executedProject('620')->asbuilt;
        Livewire::test(ViewAsbuilt::class, ['record' => $other->getRouteKey()])->assertActionHidden('delete');
    }

    #[Test]
    public function the_asbuilt_page_shows_the_project_details_also_without_files(): void
    {
        $project = $this->executedProject();
        $project->update(['short_description' => 'Remplacement de TGBT']);
        $project->poNumbers()->create(['number' => '4500012345']);

        $this->actingAs($this->manager())
            ->get(AsbuiltResource::getUrl('view', ['record' => $project->asbuilt]))
            ->assertOk()
            ->assertSee(__('erp.asbuilt.project_details'))
            ->assertSee('02GAM')
            ->assertSee('Remplacement de TGBT')
            ->assertSee('4500012345')
            ->assertSee('data-asbuilt-files', false)
            // Livewire cannot parse a click with raw quotes ("Missing closing parenthesis"): the upload per category uses Js::from.
            ->assertSee("mountAction('upload', JSON.parse(", false)
            ->assertDontSee('mountAction(\'upload\', {&quot;', false);
    }

    #[Test]
    public function a_battery_room_has_its_own_asbuilt_files_and_takes_the_wiring_diagram_of_its_closed_604(): void
    {
        $roomPlans = $this->library(AsbuiltCategory::RoomPlans, $this->site, 'plannen-02GAM.pdf');
        $ventilation = $this->library(AsbuiltCategory::VentilationCalculations, $this->site, 'ventilatie-02GAM.pdf');
        $evacuation = $this->library(AsbuiltCategory::RoomEvacuationLists, $this->site, 'evacuatielijst-02GAM.pdf');
        $otherSite = $this->library(AsbuiltCategory::RoomPlans, WorkSite::factory()->create(), 'plannen-ander.pdf');
        $roomExternal = $this->library(AsbuiltCategory::RoomExternalInfluences, null, 'extern-lokalen.pdf');
        // The ordinary as-built files stay out of a battery room, and the other way around.
        $external = $this->library(AsbuiltCategory::ExternalInfluences, null, 'extern.pdf');
        $routes = $this->library(AsbuiltCategory::EvacuationRoutes, $this->site, 'evacuatie-02GAM.pdf');

        $battery = Project::factory()->inCategory('604')->for($this->site)->create(['status' => ProjectStatus::InProgress]);
        BatteryDossier::factory()->create(['project_id' => $battery->id]);
        $room = Project::factory()->inCategory('609')->for($this->site)->create(['status' => ProjectStatus::InProgress, 'source_project_id' => $battery->id]);
        $room->teams()->attach($this->team);
        $room->update(['status' => ProjectStatus::Executed]);
        $asbuilt = $room->refresh()->asbuilt;

        $this->assertSame(AsbuiltCategory::forBatteryRoom(), $asbuilt->categories());
        $this->assertEqualsCanonicalizing([$roomPlans->id, $ventilation->id, $evacuation->id, $roomExternal->id], $asbuilt->files()->pluck('asbuilt_library_file_id')->filter()->values()->all());
        $this->assertNotContains($otherSite->id, $asbuilt->files()->pluck('asbuilt_library_file_id')->all());
        $this->assertSame(0, $asbuilt->filesIn(AsbuiltCategory::WiringDiagrams)->count(), 'The 604 is not closed yet.');

        $ordinary = $this->executedProject()->asbuilt;
        $this->assertEqualsCanonicalizing([$external->id, $routes->id], $ordinary->files()->pluck('asbuilt_library_file_id')->all());

        // Closing the 604: its wiring diagram (single-line diagram) goes into the battery room's as-built.
        $battery->update(['status' => ProjectStatus::Closed]);
        $wiring = $asbuilt->filesIn(AsbuiltCategory::WiringDiagrams)->sole();
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($wiring->path));
        $this->assertStringContainsString('eendraadschema', $wiring->original_name);
        $this->assertFalse(app(Asbuilts::class)->attachWiringDiagram($asbuilt->refresh()), 'Only once.');

        // Uploads: only the battery room's categories; edited floor plans are a plain upload zone.
        $this->actingAs($this->manager());
        Storage::disk('local')->put('asbuilt/'.$asbuilt->id.'/grondplan.pdf', 'pdf');
        Storage::disk('local')->put('asbuilt/'.$asbuilt->id.'/kabels.xlsx', 'xlsx');
        app(Asbuilts::class)->addFiles($asbuilt, AsbuiltCategory::EditedFloorPlans->value, ['asbuilt/'.$asbuilt->id.'/grondplan.pdf'], [], auth()->user());
        $this->assertSame(1, $asbuilt->filesIn(AsbuiltCategory::EditedFloorPlans)->count());
        $this->expectException(ValidationException::class);
        app(Asbuilts::class)->addFiles($asbuilt, AsbuiltCategory::CableDimensions->value, ['asbuilt/'.$asbuilt->id.'/kabels.xlsx'], [], auth()->user());
    }

    #[Test]
    public function the_battery_room_zip_has_its_own_folders(): void
    {
        $this->library(AsbuiltCategory::VentilationCalculations, $this->site, 'ventilatie.pdf');
        $room = Project::factory()->inCategory('609')->for($this->site)->create(['status' => ProjectStatus::InProgress]);
        $room->teams()->attach($this->team);
        $room->update(['status' => ProjectStatus::Executed]);
        $asbuilt = $room->refresh()->asbuilt;

        $this->actingAs($this->manager());
        Livewire::test(ViewAsbuilt::class, ['record' => $asbuilt->getRouteKey()])
            ->assertSee(AsbuiltCategory::VentilationCalculations->getLabel())
            ->assertSee(AsbuiltCategory::WiringDiagrams->getLabel())
            ->assertDontSee(AsbuiltCategory::CableDimensions->getLabel())
            ->assertActionHidden('technical');

        app(Asbuilts::class)->close($asbuilt);
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($asbuilt->refresh()->zip_path));
        $names = array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));

        foreach (['0 - BTV control/', '1 - plans/', '2 - external influences/', '4 - wiring diagram/', '5 - evacuation list/', '6 - edited floor plans/'] as $folder) {
            $this->assertContains($folder, $names);
        }
        $this->assertContains('3 - ventilation calculation/ventilatie.pdf', $names);
        $this->assertNotContains('1 - cable dimensions/', $names);
    }
}
