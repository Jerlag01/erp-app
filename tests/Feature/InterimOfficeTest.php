<?php

namespace Tests\Feature;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Enums\MailTemplateKey;
use App\Filament\Admin\Resources\InterimOffices\Pages\ManageInterimOffices;
use App\Filament\Pages\InterimTimesheetVerification;
use App\Mail\SystemMail;
use App\Models\Employee;
use App\Models\InterimOffice;
use App\Models\Role;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterimOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected const MONDAY = '2026-10-26';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-03 09:00');
    }

    protected function employee(string $name, bool $interim = false, ?InterimOffice $office = null): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => strtolower($name).'@example.com']);
        Employee::factory()->for($user)->create(['is_interim' => $interim, 'interim_office_id' => $office?->id]);
        $rows = Timesheets::emptyRows();
        $rows[1] = [...$rows[1], 'arrival' => '07:00', 'departure' => '15:30'];
        TimesheetDay::query()->create(['user_id' => $user->id, 'date' => self::MONDAY, 'rows' => $rows, 'lunch_break' => true]);

        return $user;
    }

    #[Test]
    public function an_administrator_manages_interim_offices_with_their_contacts(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageInterimOffices::class)
            ->callAction('create', [
                'name' => 'Randstad Gent',
                'is_active' => true,
                'contacts' => [
                    ['name' => 'Sofie Peeters', 'email' => 'sofie@randstad.example'],
                    ['name' => 'Tom Claes', 'email' => 'tom@randstad.example'],
                ],
            ])
            ->assertHasNoActionErrors();

        $office = InterimOffice::query()->sole();
        $this->assertSame(['sofie@randstad.example' => 'Sofie Peeters', 'tom@randstad.example' => 'Tom Claes'], $office->contactAddresses());

        // A contact needs a valid e-mail address.
        Livewire::test(ManageInterimOffices::class)
            ->callAction('create', ['name' => 'Adecco', 'contacts' => [['name' => 'X', 'email' => 'not-an-address']]])
            ->assertHasActionErrors();

        // The employee keeps the office's name; only interims have an office.
        $employee = Employee::factory()->create(['is_interim' => true, 'interim_office_id' => $office->id]);
        $this->assertSame('Randstad Gent', $employee->interim_agency);
        $employee->update(['is_interim' => false]);
        $this->assertNull($employee->fresh()->interim_office_id);
        $this->assertNull($employee->fresh()->interim_agency);
    }

    #[Test]
    public function interim_sheets_wait_for_verification_and_then_go_to_their_office(): void
    {
        Mail::fake();
        settings()->save('timesheets', [...settings('timesheets'), 'recipients' => ['payroll@example.com'], 'send_to_employee' => false]);
        $randstad = InterimOffice::factory()->create(['name' => 'Randstad']);
        $randstad->contacts()->createMany([['name' => 'Sofie', 'email' => 'sofie@randstad.example'], ['name' => 'Tom', 'email' => 'tom@randstad.example']]);
        $adecco = InterimOffice::factory()->create(['name' => 'Adecco']);
        $adecco->contacts()->create(['name' => 'Lien', 'email' => 'lien@adecco.example']);
        $empty = InterimOffice::factory()->create(['name' => 'Zonder contacten']);

        $anna = $this->employee('Anna', true, $randstad);
        $this->employee('Bert', true, $randstad);
        $this->employee('Cas', true, $adecco);
        $this->employee('Dirk', true, $empty);
        $this->employee('Eva');

        // Who verifies: administrators and the role Administratie; not an ordinary employee.
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'locale' => 'nl']);
        $administration = User::factory()->create(['email' => 'adm@example.com', 'role_id' => Role::query()->where('name', 'Administratie')->sole()->id]);
        $employee = User::factory()->withPermissions(['timesheets' => ['see_all']])->inTeam()->create(['email' => 'emp@example.com']);

        $result = app(SendWeeklyTimesheets::class)->handle(CarbonImmutable::parse(self::MONDAY));
        $this->assertSame([true, 5, 3], [$result['sent'], $result['count'], $result['interim']]);

        // The general addresses get everyone at once; the offices nothing yet.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::TimesheetWeek && $mail->hasTo('payroll@example.com') && count($mail->files) === 5);
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sofie@randstad.example') || $mail->hasTo('lien@adecco.example'));

        // The verifiers: a notification in the platform and an e-mail with a link.
        foreach ([$admin, $administration] as $verifier) {
            $this->assertSame(1, $verifier->notifications()->count());
            Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::TimesheetInterimVerify && $mail->hasTo($verifier->email)
                && str_contains($mail->rendered['html'], 'Randstad') && str_contains($mail->rendered['html'], InterimTimesheetVerification::getUrl(['week' => self::MONDAY], panel: 'app')));
        }
        $this->assertSame(0, $employee->notifications()->count());
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('emp@example.com'));
        Mail::assertSent(SystemMail::class, 3);

        // The verification page: only for those who verify; the sheets per office.
        Filament::setCurrentPanel('app');
        $this->actingAs($employee)->get(InterimTimesheetVerification::getUrl(panel: 'app'))->assertForbidden();
        $this->actingAs($administration)->get(InterimTimesheetVerification::getUrl(panel: 'app'))->assertOk()->assertSee('Randstad')->assertSee('Anna')->assertSee('Cas');

        // They may open and correct the interim's sheet while it waits.
        $this->assertTrue(app(Timesheets::class)->canEdit($administration, $anna, TimesheetWeek::query()->where('user_id', $anna->id)->sole()));

        Livewire::test(InterimTimesheetVerification::class)
            ->callAction(TestAction::make('verify')->arguments(['office' => $randstad->id, 'week' => self::MONDAY]))
            ->assertNotified();

        // Randstad: only its two interims, to both contacts; verified by whom.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::TimesheetWeek
            && $mail->hasTo('sofie@randstad.example') && $mail->hasTo('tom@randstad.example') && ! $mail->hasTo('payroll@example.com')
            && count($mail->files) === 2
            && str_contains($mail->rendered['html'], 'Anna') && str_contains($mail->rendered['html'], 'Bert') && ! str_contains($mail->rendered['html'], 'Cas'));
        $week = TimesheetWeek::query()->where('user_id', $anna->id)->sole();
        $this->assertSame($administration->id, $week->interim_verified_by);
        $this->assertNotNull($week->interim_sent_at);
        $this->assertFalse(app(Timesheets::class)->canEdit($administration, $anna, $week));
        $this->assertSame(1, TimesheetWeek::query()->awaitingInterimVerification()->count());
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('lien@adecco.example'));

        // Sent again by the schedule: what went to the office is not held again.
        app(SendWeeklyTimesheets::class)->handle(CarbonImmutable::parse(self::MONDAY), [$anna]);
        $this->assertNotNull($week->fresh()->interim_sent_at);
        $this->assertNotNull($week->fresh()->interim_verified_at);
    }

    #[Test]
    public function interim_contacts_alone_are_enough_to_send(): void
    {
        Mail::fake();
        settings()->save('timesheets', [...settings('timesheets'), 'recipients' => [], 'send_to_employee' => false]);
        $office = InterimOffice::factory()->create();
        $office->contacts()->create(['name' => 'Sofie', 'email' => 'sofie@randstad.example']);
        $anna = $this->employee('Anna', true, $office);

        $result = app(SendWeeklyTimesheets::class)->handle(CarbonImmutable::parse(self::MONDAY), [$anna]);

        $this->assertSame([true, 1], [$result['sent'], $result['interim']]);
        $this->assertSame(1, TimesheetWeek::query()->awaitingInterimVerification()->count());
    }
}
