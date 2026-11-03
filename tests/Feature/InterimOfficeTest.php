<?php

namespace Tests\Feature;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Enums\MailTemplateKey;
use App\Filament\Admin\Resources\InterimOffices\Pages\ManageInterimOffices;
use App\Mail\SystemMail;
use App\Models\Employee;
use App\Models\InterimOffice;
use App\Models\TimesheetDay;
use App\Models\User;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
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
    public function the_contacts_of_the_interim_office_get_the_weekly_sheets_of_its_interims(): void
    {
        Mail::fake();
        settings()->save('timesheets', [...settings('timesheets'), 'recipients' => ['payroll@example.com'], 'send_to_employee' => false]);
        $randstad = InterimOffice::factory()->create(['name' => 'Randstad']);
        $randstad->contacts()->createMany([['name' => 'Sofie', 'email' => 'sofie@randstad.example'], ['name' => 'Tom', 'email' => 'tom@randstad.example']]);
        $adecco = InterimOffice::factory()->create(['name' => 'Adecco']);
        $adecco->contacts()->create(['name' => 'Lien', 'email' => 'lien@adecco.example']);
        $empty = InterimOffice::factory()->create(['name' => 'Zonder contacten']);

        $this->employee('Anna', true, $randstad);
        $this->employee('Bert', true, $randstad);
        $this->employee('Cas', true, $adecco);
        $this->employee('Dirk', true, $empty);
        $this->employee('Eva');

        $result = app(SendWeeklyTimesheets::class)->handle(CarbonImmutable::parse(self::MONDAY));
        $this->assertSame([true, 5, 2], [$result['sent'], $result['count'], $result['interim']]);

        // The general addresses: everyone.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::TimesheetWeek && $mail->hasTo('payroll@example.com') && count($mail->files) === 5);

        // Randstad: only its two interims, to both contacts.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sofie@randstad.example') && $mail->hasTo('tom@randstad.example')
            && ! $mail->hasTo('payroll@example.com')
            && count($mail->files) === 2
            && str_contains($mail->rendered['html'], 'Anna') && str_contains($mail->rendered['html'], 'Bert') && ! str_contains($mail->rendered['html'], 'Cas'));

        // Adecco: only Cas.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('lien@adecco.example') && count($mail->files) === 1 && str_contains($mail->files[0]['name'], 'cas'));
        Mail::assertSent(SystemMail::class, 3);
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
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sofie@randstad.example'));
    }
}
