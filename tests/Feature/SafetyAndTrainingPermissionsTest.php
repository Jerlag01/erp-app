<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Filament\Admin\Pages\TrainingMatrix;
use App\Filament\Admin\Resources\Employees\RelationManagers\CertificatesRelationManager;
use App\Filament\Admin\Resources\Employees\RelationManagers\MedicalChecksRelationManager;
use App\Filament\Admin\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Admin\Resources\Incidents\Pages\ViewIncident;
use App\Filament\App\Pages\ReportIncident;
use App\Filament\App\Resources\EmployeeTrainings\EmployeeTrainingResource;
use App\Filament\App\Resources\EmployeeTrainings\Pages\ViewEmployeeTraining;
use App\Models\Employee;
use App\Models\EmployeeMedicalCheck;
use App\Models\Incident;
use App\Models\Location;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SafetyAndTrainingPermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_regular_incident_can_be_reported(): void
    {
        $this->actingAs(User::factory()->inTeam()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(ReportIncident::class)
            ->fillForm([
                'type' => IncidentType::Regular->value,
                'place_type' => 'location',
                'place_id' => Location::factory()->create()->id,
                'description' => 'De poort van het depot sloot niet meer.',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame(IncidentType::Regular, Incident::query()->sole()->type);
        $this->assertSame('Gewoon incident', __('erp.enums.incident_type.regular', [], 'nl'));
    }

    #[Test]
    public function the_permissions_exist_for_roles(): void
    {
        $roles = Permissions::forRoles();

        $this->assertSame(['view', 'update', 'delete', 'generate_pdf'], $roles['incidents']);
        $this->assertSame(['view', 'create', 'update', 'delete'], $roles['certificates']);
        $this->assertSame(['view', 'create', 'update', 'delete'], $roles['medical_checks']);
        $this->assertContains('create', $roles['toolbox']);
    }

    #[Test]
    public function employees_with_the_incident_permissions_see_and_handle_everyones_incidents(): void
    {
        $reported = Incident::factory()->count(2)->create();
        $own = User::factory()->inTeam()->create();
        $ownIncident = Incident::factory()->for($own)->create();
        Filament::setCurrentPanel('app');

        // Without the permission: not the list, nor someone else's report.
        $this->actingAs($own)->get('/incidents')->assertForbidden();
        $this->get("/incidents/{$reported[0]->id}")->assertForbidden();

        // "view": every report, no handling, no PDF.
        $viewer = User::factory()->withPermissions(['incidents' => ['view']])->inTeam()->create();
        $this->actingAs($viewer)->get('/incidents')->assertOk();
        Livewire::test(ListIncidents::class)->assertCanSeeTableRecords([...$reported, $ownIncident]);
        Livewire::test(ViewIncident::class, ['record' => $reported[0]->getRouteKey()])
            ->assertActionHidden('handle')
            ->assertActionHidden('pdf')
            ->assertActionHidden('delete');
        $this->get(route('incidents.pdf', ['incidents' => [$reported[0]->id]]))->assertForbidden();

        // "update" handles them, "delete" removes them, "generate_pdf" exports them.
        $handler = User::factory()->withPermissions(['incidents' => ['view', 'update', 'delete', 'generate_pdf']])->inTeam()->create();
        $this->actingAs($handler);
        Livewire::test(ViewIncident::class, ['record' => $reported[0]->getRouteKey()])
            ->callAction('handle', ['status' => 'closed', 'follow_up' => 'Opgelost.'])
            ->assertHasNoActionErrors();
        $this->assertSame(IncidentStatus::Closed, $reported[0]->fresh()->status);
        $this->get(route('incidents.pdf', ['incidents' => [$reported[0]->id]]))->assertOk();
        Livewire::test(ViewIncident::class, ['record' => $reported[1]->getRouteKey()])->callAction('delete');
        $this->assertModelMissing($reported[1]);
    }

    #[Test]
    public function new_incidents_are_notified_to_those_who_see_them(): void
    {
        $viewer = User::factory()->withPermissions(['incidents' => ['view']])->inTeam()->create();
        $other = User::factory()->inTeam()->create();
        $this->actingAs(User::factory()->inTeam()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(ReportIncident::class)
            ->fillForm(['type' => IncidentType::Regular->value, 'place_type' => 'location', 'place_id' => Location::factory()->create()->id, 'description' => 'Lek in het dak.'])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $viewer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    #[Test]
    public function the_training_overview_follows_the_certificate_and_medical_permissions(): void
    {
        $employee = Employee::factory()->for(User::factory())->create(['first_name' => 'Jeroen', 'last_name' => 'Lagaet']);
        $employee->certificates()->create(['type' => CertificateType::Vca, 'expires_on' => today()->addYear()]);
        EmployeeMedicalCheck::query()->create(['employee_id' => $employee->id, 'checked_on' => today()->subMonth(), 'result' => 'fit']);
        Filament::setCurrentPanel('app');

        // Nobody else: not in the employee portal.
        $this->actingAs(User::factory()->inTeam()->create())->get(TrainingMatrix::getUrl(panel: 'app'))->assertForbidden();

        // Only the certificates: their columns, not the medical one; read-only.
        $certificates = User::factory()->withPermissions(['certificates' => ['view']])->inTeam()->create();
        $this->actingAs($certificates)->get(TrainingMatrix::getUrl(panel: 'app'))->assertOk()
            ->assertSee('Jeroen Lagaet')->assertSee(EmployeeTrainingResource::getUrl('view', ['record' => $employee], panel: 'app'))
            ->assertDontSee(__('erp.training_matrix.columns.medical'));
        $this->get(EmployeeTrainingResource::getUrl('view', ['record' => $employee], panel: 'app'))->assertOk();
        Livewire::test(CertificatesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewEmployeeTraining::class])
            ->assertOk()
            ->assertActionHidden(TestAction::make(CreateAction::class)->table());
        $this->assertFalse(MedicalChecksRelationManager::canViewForRecord($employee, ViewEmployeeTraining::class));
        // Nothing else of the employee register.
        $this->get('/admin/employees/'.$employee->id)->assertForbidden();

        // The medical check-ups with all rights.
        $medical = User::factory()->withPermissions(['medical_checks' => ['view', 'create', 'update', 'delete']])->inTeam()->create();
        $this->actingAs($medical)->get(TrainingMatrix::getUrl(panel: 'app'))->assertOk()->assertSee(__('erp.training_matrix.columns.medical'));
        $this->assertFalse(CertificatesRelationManager::canViewForRecord($employee, ViewEmployeeTraining::class));
        $check = $employee->medicalChecks()->sole();
        Livewire::test(MedicalChecksRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewEmployeeTraining::class])
            ->assertActionVisible(TestAction::make(CreateAction::class)->table())
            ->assertActionVisible(TestAction::make(EditAction::class)->table($check))
            ->callAction(TestAction::make(DeleteAction::class)->table($check));
        $this->assertModelMissing($check);
    }
}
