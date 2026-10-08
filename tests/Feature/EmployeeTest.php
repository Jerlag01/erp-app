<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\MedicalResult;
use App\Filament\Admin\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\CertificatesRelationManager;
use App\Filament\Admin\Resources\Employees\RelationManagers\MedicalChecksRelationManager;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\WelcomeEmployee;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A complete, valid employee form.
     *
     * @return array<string, mixed>
     */
    protected function employeeData(array $overrides = []): array
    {
        return [
            'first_name' => 'Eva',
            'last_name' => 'Peeters',
            'street' => 'Kerkstraat',
            'house_number' => '12',
            'postal_code' => '2800',
            'city' => 'Mechelen',
            'private_email' => 'eva.peeters@example.com',
            'private_phone' => '0470 12 34 56',
            'national_number' => '85.07.30-033.28',
            'bank_account' => 'BE68 5390 0754 7034',
            'place_of_birth' => 'Leuven',
            'date_of_birth' => '1985-07-30',
            'mother_tongue' => 'nl',
            'employment_date' => '2026-10-01',
            'employment_category' => 'worker',
            'contract_term' => 'indefinite',
            'education_level' => 'higher_secondary',
            'size_pants' => '50',
            'size_shirt' => 'L',
            'size_sweater' => 'XL',
            'size_shoes' => '43',
            'emergency1_first_name' => 'Jan',
            'emergency1_last_name' => 'Peeters',
            'emergency1_phone' => '0475 11 22 33',
            'emergency1_relation' => 'Partner',
            ...$overrides,
        ];
    }

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    #[Test]
    public function adding_an_employee_creates_the_login_and_sends_the_welcome_mail(): void
    {
        Notification::fake();
        $this->admin();
        $team = Team::factory()->create();
        $role = Role::factory()->create(['name' => 'Medewerker']);

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->employeeData(['role_id' => $role->id, 'teams' => [$team->id], 'locale' => 'fr']))
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = Employee::sole();
        $user = $employee->user;
        $this->assertSame('Eva Peeters', $user->name);
        $this->assertSame('eva.peeters@example.com', $user->email, 'Without a login e-mail the private address is used.');
        $this->assertTrue($user->role->is($role));
        $this->assertSame([$team->id], $user->teamIds());
        $this->assertSame('fr', $user->locale);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($employee->welcome_sent_at);
        Notification::assertSentTo($user, WelcomeEmployee::class);

        // Sensitive fields are stored encrypted and normalised.
        $this->assertSame('85073003328', $employee->national_number);
        $this->assertSame('BE68539007547034', $employee->bank_account);
        $this->assertStringNotContainsString('85073003328', (string) $employee->getRawOriginal('national_number'));
    }

    #[Test]
    public function the_welcome_link_leads_to_choosing_a_password_and_works_until_one_is_set(): void
    {
        $user = User::factory()->create(['email' => 'nieuw@example.com']);
        $link = WelcomeEmployee::linkFor($user);

        $mail = (new WelcomeEmployee)->toMail($user);
        $this->assertSame($link, $mail->actionUrl);
        $this->assertStringContainsString('nieuw@example.com', implode(' ', $mail->introLines));

        $this->get($link)->assertRedirectContains('password-reset');

        $user->update(['password' => Hash::make('Een-nieuw-wachtwoord-1')]);
        $this->get($link)->assertRedirect(Filament::getPanel('app')->getLoginUrl());

        $this->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();
    }

    #[Test]
    public function invalid_national_numbers_and_ibans_are_refused(): void
    {
        $this->admin();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->employeeData(['national_number' => '85.07.30-033.29', 'bank_account' => 'BE68 5390 0754 7035']))
            ->call('create')
            ->assertHasFormErrors(['national_number', 'bank_account']);

        $this->assertSame(0, Employee::count());
        $this->assertTrue(Employee::isValidNationalNumber('00012556777'), 'Born in 2000 or later uses the 2-prefix check.');
    }

    #[Test]
    public function the_first_emergency_contact_is_required_and_the_second_complete_or_empty(): void
    {
        $this->admin();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->employeeData(['emergency1_phone' => null, 'emergency2_first_name' => 'Mia']))
            ->call('create')
            ->assertHasFormErrors(['emergency1_phone' => 'required', 'emergency2_phone' => 'required', 'emergency2_relation' => 'required'])
            ->assertHasNoFormErrors(['emergency2_first_name']);
    }

    #[Test]
    public function an_existing_account_can_be_linked_without_a_welcome_mail(): void
    {
        Notification::fake();
        $this->admin();
        $existing = User::factory()->create(['email' => 'eva.peeters@example.com']);

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->employeeData(['link_existing' => true, 'existing_user_id' => $existing->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Employee::sole()->user->is($existing));
        $this->assertSame(1, User::where('email', 'eva.peeters@example.com')->count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function an_address_that_already_has_an_account_is_refused(): void
    {
        $this->admin();
        User::factory()->create(['email' => 'eva.peeters@example.com']);

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->employeeData())
            ->call('create')
            ->assertHasErrors(['data.login_email']);

        $this->assertSame(0, Employee::count());
    }

    #[Test]
    public function accounts_can_still_be_made_without_the_employee_module(): void
    {
        $this->setModules(['employees' => false]);
        $this->admin();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Tijdelijke beheerder', 'email' => 'tijdelijk@example.com', 'password' => 'Een-sterk-wachtwoord-1', 'role_id' => Role::factory()->admin()->create()->id, 'locale' => 'nl', 'vacation_days' => 20])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(User::where('email', 'tijdelijk@example.com')->exists());
        $this->get('/admin/employees')->assertForbidden();
    }

    #[Test]
    public function an_employee_who_leaves_loses_the_login_but_keeps_all_details(): void
    {
        $this->admin();
        $employee = Employee::factory()->create(['private_phone' => '0470 99 99 99']);
        $user = $employee->user;

        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction('endEmployment', ['left_on' => '2026-10-31', 'leaving_reason' => 'Eigen ontslag'])
            ->assertHasNoActionErrors();

        $employee->refresh();
        $this->assertFalse($employee->isEmployed());
        $this->assertSame('0470 99 99 99', $employee->private_phone);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('app')));

        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction('rehire');

        $this->assertTrue($employee->fresh()->isEmployed());
        $this->assertTrue($user->fresh()->is_active);
    }

    #[Test]
    public function certificates_have_an_optional_expiry_date_and_a_pdf(): void
    {
        Storage::fake('local');
        $this->admin();
        $employee = Employee::factory()->create();
        $manager = fn () => Livewire::test(CertificatesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewEmployee::class]);

        $manager()->callAction(TestAction::make('create')->table(), [
            'type' => CertificateType::Vca->value,
            'expires_on' => today()->addDays(20)->toDateString(),
            'document' => UploadedFile::fake()->create('vca.pdf', 30, 'application/pdf'),
        ])->assertHasNoFormErrors();

        $manager()->callAction(TestAction::make('create')->table(), [
            'type' => CertificateType::DrivingLicence->value,
            'categories' => ['B', 'BE', 'C'],
        ])->assertHasNoFormErrors();

        $manager()->callAction(TestAction::make('create')->table(), ['type' => CertificateType::Vca->value])
            ->assertHasFormErrors(['type' => 'unique']);

        $vca = $employee->certificates()->where('type', 'vca')->sole();
        $this->assertTrue($vca->expiresSoon());
        $this->assertSame('vca.pdf', $vca->document_name);
        $licence = $employee->certificates()->where('type', 'driving_licence')->sole();
        $this->assertNull($licence->expires_on);
        $this->assertSame('Rijbewijs B, BE, C', $licence->label());

        $url = route('employees.document', ['employee' => $employee, 'kind' => 'certificate', 'id' => $vca->id]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->inTeam()->create())->get($url)->assertForbidden();
    }

    #[Test]
    public function the_yearly_medical_check_up_is_followed_up(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();
        $this->assertTrue($employee->medicalOverdue(), 'Without any check-up it is due straight away.');

        Livewire::test(MedicalChecksRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewEmployee::class])
            ->callAction(TestAction::make('create')->table(), [
                'checked_on' => today()->subMonth()->toDateString(),
                'next_due_on' => null,
                'result' => MedicalResult::FitWithRestrictions->value,
                'remarks' => 'Niet zwaar tillen',
            ])
            ->assertHasNoFormErrors();

        $employee->refresh();
        $this->assertFalse($employee->medicalOverdue());
        $this->assertSame(today()->subMonth()->addYear()->toDateString(), $employee->nextMedicalDue()->toDateString());
    }

    #[Test]
    public function the_register_is_for_administrators_only_and_can_be_switched_off(): void
    {
        $employee = Employee::factory()->create();
        $nonAdmin = User::factory()->withPermissions(['teams' => ['view']])->inTeam()->create();

        $this->actingAs($nonAdmin)->get('/admin/employees')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/employees')->assertOk()->assertSee($employee->last_name);
        $this->actingAs($admin)->get('/admin/employees/create')->assertOk();
        $this->actingAs($admin)->get("/admin/employees/{$employee->id}")->assertOk()->assertSee('Partner');

        $this->setModules(['employees' => false]);
        $this->actingAs($admin)->get('/admin/employees')->assertForbidden();
    }
}
