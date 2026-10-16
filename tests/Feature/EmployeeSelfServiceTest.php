<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\RequestStatus;
use App\Filament\Admin\Resources\EmployeeCertificateRequests\Pages\ManageEmployeeCertificateRequests;
use App\Filament\Admin\Resources\EmployeeChangeRequests\Pages\ManageEmployeeChangeRequests;
use App\Filament\App\Pages\MyDetails;
use App\Models\Employee;
use App\Models\EmployeeCertificateRequest;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $employee;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $user = User::factory()->withPermissions([])->inTeam()->create();
        $this->employee = Employee::factory()->for($user)->create(['city' => 'Mechelen', 'bank_account' => null]);
    }

    protected function asEmployee(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAs($this->employee->user);
    }

    protected function asAdmin(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin);
    }

    #[Test]
    public function employees_change_their_own_details_but_only_after_approval(): void
    {
        $this->asEmployee();
        $this->get('/my-details')->assertOk()->assertSee('Mechelen');

        Livewire::test(MyDetails::class)
            ->assertSchemaStateSet(['city' => 'Mechelen'])
            ->fillForm(['city' => 'Leuven', 'postal_code' => '3000', 'bank_account' => 'be68 5390 0754 7034'])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame('Mechelen', $this->employee->fresh()->city, 'Nothing changes before approval.');
        $request = EmployeeChangeRequest::sole();
        $this->assertSame(['postal_code' => '3000', 'city' => 'Leuven', 'bank_account' => 'BE68539007547034'], $request->new_values);
        $this->assertSame('Mechelen', $request->old_values['city']);
        $this->assertSame(1, $this->admin->notifications()->count());

        // Editing again replaces the pending request; the form shows the pending values.
        Livewire::test(MyDetails::class)
            ->assertSchemaStateSet(['city' => 'Leuven'])
            ->fillForm(['city' => 'Heverlee'])
            ->call('submit');
        $this->assertSame('Heverlee', EmployeeChangeRequest::sole()->new_values['city']);

        // Invalid values are refused like in the register.
        Livewire::test(MyDetails::class)->fillForm(['bank_account' => 'BE00 1234'])->call('submit')->assertHasFormErrors(['bank_account']);

        $this->asAdmin();
        $this->get('/admin/employee-change-requests')->assertOk()->assertSee($this->employee->fullName());
        Livewire::test(ManageEmployeeChangeRequests::class)->callTableAction('approve', EmployeeChangeRequest::sole())->assertHasNoTableActionErrors();

        $employee = $this->employee->fresh();
        $this->assertSame(['Heverlee', '3000', 'BE68539007547034'], [$employee->city, $employee->postal_code, $employee->bank_account]);
        $this->assertSame(RequestStatus::Approved, EmployeeChangeRequest::sole()->status);
        $this->assertSame(1, $this->employee->user->notifications()->count());
    }

    #[Test]
    public function a_rejected_or_empty_request_changes_nothing(): void
    {
        $this->asEmployee();
        Livewire::test(MyDetails::class)->call('submit');
        $this->assertSame(0, EmployeeChangeRequest::query()->count(), 'Without changes nothing is submitted.');

        Livewire::test(MyDetails::class)->fillForm(['size_shoes' => '44'])->call('submit');

        $this->asAdmin();
        Livewire::test(ManageEmployeeChangeRequests::class)->callTableAction('reject', EmployeeChangeRequest::sole(), ['reason' => 'Klopt niet']);

        $this->assertNull($this->employee->fresh()->size_shoes);
        $this->assertSame([RequestStatus::Rejected, 'Klopt niet'], [EmployeeChangeRequest::sole()->status, EmployeeChangeRequest::sole()->rejection_reason]);
    }

    #[Test]
    public function only_employees_in_the_register_have_the_page(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAs(User::factory()->withPermissions([])->inTeam()->create())->get('/my-details')->assertForbidden();

        $this->employee->update(['left_on' => today()]);
        $this->actingAs($this->employee->user)->get('/my-details')->assertForbidden();

        $this->asEmployee();
        $this->get('/admin/employee-change-requests')->assertForbidden();
    }

    #[Test]
    public function employees_add_their_own_certificates_for_approval(): void
    {
        $this->employee->certificates()->create(['type' => CertificateType::Vca, 'expires_on' => '2026-01-01']);

        $this->asEmployee();
        Livewire::test(MyDetails::class)
            ->callAction('addCertificate', [
                'type' => CertificateType::Vca->value,
                'obtained_on' => '2026-09-01',
                'expires_on' => '2036-09-01',
                'document' => UploadedFile::fake()->create('vca-2026.pdf', 30, 'application/pdf'),
            ])
            ->assertHasNoActionErrors()
            ->callAction('addCertificate', ['type' => CertificateType::SchoolDegree->value, 'notes' => 'Bachelor elektromechanica'])
            ->assertHasNoActionErrors();

        $this->assertSame('2026-01-01', $this->employee->certificates()->sole()->expires_on->toDateString(), 'Nothing changes before approval.');
        $vca = EmployeeCertificateRequest::query()->where('type', 'vca')->sole();
        Storage::disk('local')->assertExists($vca->document);
        $this->get('/my-details')->assertSee(__('erp.enums.request_status.pending'));

        $this->asAdmin();
        $this->get(route('employees.document', ['employee' => $this->employee, 'kind' => 'request', 'id' => $vca->id]))->assertOk();
        Livewire::test(ManageEmployeeCertificateRequests::class)
            ->callTableAction('approve', $vca)
            ->callTableAction('reject', EmployeeCertificateRequest::query()->where('type', 'school_degree')->sole(), ['reason' => 'Diploma ontbreekt']);

        $certificate = $this->employee->certificates()->sole();
        $this->assertSame(['2036-09-01', 'vca-2026.pdf'], [$certificate->expires_on->toDateString(), $certificate->document_name], 'The VCA was renewed.');
        $this->assertSame(RequestStatus::Rejected, EmployeeCertificateRequest::query()->where('type', 'school_degree')->sole()->status);
        $this->assertSame(2, $this->employee->user->notifications()->count());
    }

    #[Test]
    public function interim_employees_do_not_get_the_emergency_and_personal_number_fields(): void
    {
        $this->employee->update(['is_interim' => true, 'emergency1_first_name' => null, 'emergency1_last_name' => null, 'emergency1_phone' => null, 'emergency1_relation' => null]);
        $this->asEmployee();

        Livewire::test(MyDetails::class)
            ->assertFormFieldHidden('national_number')
            ->assertFormFieldHidden('bank_account')
            ->assertFormFieldHidden('date_of_birth')
            ->assertFormFieldHidden('emergency1_first_name')
            ->fillForm(['city' => 'Lier'])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame(['city' => 'Lier'], EmployeeChangeRequest::sole()->new_values);
    }
}
