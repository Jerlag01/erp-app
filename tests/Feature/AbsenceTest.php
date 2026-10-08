<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Filament\Admin\Resources\Absences\Pages\ManageAbsences;
use App\Filament\App\Resources\MyAbsences\Pages\ListMyAbsences;
use App\Models\Absence;
use App\Models\Team;
use App\Models\User;
use App\Support\VacationCalendar;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_administrators_enter_absences(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageAbsences::class)
            ->set('activeTab', 'medical')
            ->callAction('create', [
                'user_id' => $employee->id,
                'type' => 'medical',
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-16',
                'document' => UploadedFile::fake()->create('attest.pdf', 40, 'application/pdf'),
            ])
            ->assertHasNoActionErrors();

        $absence = Absence::sole();
        $this->assertSame('5.0', $absence->days);
        $this->assertSame(Absence::SOURCE_ADMIN, $absence->document_source);
        $this->assertSame('attest.pdf', $absence->document_name);

        $this->actingAs(User::factory()->withPermissions(['vacations' => ['view', 'approve']])->inTeam()->create())
            ->get('/admin/absences')->assertForbidden();
    }

    #[Test]
    public function the_employee_sees_their_absences_per_kind(): void
    {
        $employee = User::factory()->inTeam()->create();
        $medical = Absence::factory()->for($employee)->create();
        $overtime = Absence::factory()->for($employee)->ofType(AbsenceType::Overtime)->create(['start_date' => '2026-11-02', 'end_date' => '2026-11-02']);
        $family = Absence::factory()->for($employee)->ofType(AbsenceType::Family)->create(['start_date' => '2026-11-05', 'end_date' => '2026-11-05']);
        $someoneElse = Absence::factory()->create();
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        $this->get('/my-absences')->assertOk();

        Livewire::test(ListMyAbsences::class)
            ->assertCanSeeTableRecords([$medical])
            ->assertCanNotSeeTableRecords([$overtime, $family, $someoneElse])
            ->set('activeTab', 'overtime')
            ->assertCanSeeTableRecords([$overtime])
            ->set('activeTab', 'family')
            ->assertCanSeeTableRecords([$family]);
    }

    #[Test]
    public function the_employee_uploads_a_missing_certificate_and_administrators_are_told(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create();
        $absence = Absence::factory()->for($employee)->create();
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(ListMyAbsences::class)
            ->callAction(TestAction::make('upload')->table($absence), ['document' => UploadedFile::fake()->image('attest.heic')->size(100)])
            ->assertHasNoFormErrors();

        $absence->refresh();
        $this->assertSame(Absence::SOURCE_EMPLOYEE, $absence->document_source);
        Storage::disk('local')->assertExists($absence->document);
        $this->assertCount(1, $admin->notifications);

        // Their own upload can still be replaced, the old file is removed.
        $first = $absence->document;
        Livewire::test(ListMyAbsences::class)
            ->callAction(TestAction::make('upload')->table($absence), ['document' => UploadedFile::fake()->create('attest.pdf', 20, 'application/pdf')])
            ->assertHasNoFormErrors();
        Storage::disk('local')->assertMissing($first);

        $this->get(route('absences.document', $absence))->assertOk();
        $this->actingAs(User::factory()->inTeam()->create())->get(route('absences.document', $absence))->assertForbidden();
    }

    #[Test]
    public function a_certificate_from_the_administrator_can_only_be_downloaded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('absences/attest.pdf', '%PDF-1.4 test');
        $employee = User::factory()->inTeam()->create();
        $absence = Absence::factory()->for($employee)->create(['document' => 'absences/attest.pdf', 'document_name' => 'attest.pdf', 'document_source' => Absence::SOURCE_ADMIN]);
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(ListMyAbsences::class)
            ->assertActionHidden(TestAction::make('upload')->table($absence))
            ->assertActionVisible(TestAction::make('download')->table($absence));

        $this->get(route('absences.document', $absence))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=attest.pdf');
        $this->assertFalse($employee->can('uploadDocument', $absence));
    }

    #[Test]
    public function colleagues_see_private_reasons_only_as_absent_in_the_calendar(): void
    {
        $team = Team::factory()->create();
        $sick = User::factory()->inTeam($team)->create(['name' => 'Eva Peeters']);
        $colleague = User::factory()->inTeam($team)->create();
        $outsider = User::factory()->inTeam()->create();
        Absence::factory()->for($sick)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        Absence::factory()->for($colleague)->ofType(AbsenceType::Overtime)->create(['start_date' => '2026-10-13', 'end_date' => '2026-10-13']);

        $entries = fn (User $viewer): array => collect(app(VacationCalendar::class)->month(2026, 10, $viewer)['weeks'])
            ->flatten(1)->flatMap(fn (array $day): array => $day['absences'])->mapWithKeys(fn (array $entry): array => [$entry['absence']->user->name => $entry])->all();

        $seenByColleague = $entries($colleague);
        $this->assertSame(__('erp.absences.absent'), $seenByColleague['Eva Peeters']['label']);
        $this->assertSame(VacationCalendar::PRIVATE_ABSENCE_COLOR, $seenByColleague['Eva Peeters']['color']);
        $this->assertSame(AbsenceType::Overtime->getLabel(), $seenByColleague[$colleague->name]['label']);

        $this->assertSame(AbsenceType::Medical->getLabel(), $entries($sick)['Eva Peeters']['label']);
        $this->assertSame(AbsenceType::Medical->getLabel(), $entries(User::factory()->admin()->create())['Eva Peeters']['label']);
        $this->assertSame([], $entries($outsider));

        $this->actingAs($colleague)->get('/vacation-calendar?month=2026-10')->assertOk()->assertSee('data-absence', escape: false)->assertSee('Eva Peeters');
    }

    #[Test]
    public function the_module_switch_also_covers_absences(): void
    {
        $employee = User::factory()->inTeam()->create();
        $this->setModules(['vacations' => false]);

        $this->actingAs($employee)->get('/my-absences')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/absences')->assertForbidden();
    }
}
