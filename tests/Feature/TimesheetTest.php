<?php

namespace Tests\Feature;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Actions\Timesheets\TimesheetPdf;
use App\Enums\AbsenceType;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Enums\VacationStatus;
use App\Filament\Admin\Pages\Settings;
use App\Filament\Pages\Timesheet;
use App\Filament\Resources\Trainings\Pages\ManageTrainings;
use App\Mail\SystemMail;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\Training;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\Vehicle;
use App\Models\WorkSite;
use App\Support\Planning;
use App\Support\TimesheetReport;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    protected const MONDAY = '2026-10-12';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Storage::fake('local');
    }

    /**
     * The example day of the Excel: 06:15–06:30 at the warehouse, 08:00–13:30 on site, an extra site
     * in between and 13:30–16:15 back: 0,25 + 5,5 + 2,75 − 0,5 lunch = 8 hours.
     *
     * @return list<array<string, mixed>>
     */
    protected function exampleRows(): array
    {
        $rows = Timesheets::emptyRows();
        $rows[0] = [...$rows[0], 'arrival' => '06:15', 'departure' => '06:30', 'km_departure' => 1000, 'km_arrival' => 1000];
        $rows[1] = [...$rows[1], 'arrival' => '08:00', 'departure' => '13:30', 'km_departure' => 1000, 'km_arrival' => 1045];
        $rows[2] = [...$rows[2], 'arrival' => '14:00', 'departure' => '15:00', 'km_departure' => 1045, 'km_arrival' => 1070];
        $rows[5] = [...$rows[5], 'arrival' => '13:30', 'departure' => '16:15', 'km_departure' => 1070, 'km_arrival' => 1110];

        return $rows;
    }

    protected function day(User $user, string $date, ?array $rows = null, bool $lunch = true): TimesheetDay
    {
        return TimesheetDay::query()->create(['user_id' => $user->id, 'date' => $date, 'rows' => $rows ?? $this->exampleRows(), 'lunch_break' => $lunch]);
    }

    #[Test]
    public function a_day_counts_start_site_and_end_but_not_the_extra_sites(): void
    {
        $user = User::factory()->create();
        $this->day($user, self::MONDAY);
        $this->day($user, '2026-10-13', lunch: false);

        $days = app(Timesheets::class)->days($user, CarbonImmutable::parse(self::MONDAY), CarbonImmutable::parse('2026-10-13'));

        $this->assertSame(8.0, $days[self::MONDAY]['total']);
        $this->assertSame(8.5, $days['2026-10-13']['total'], 'Without lunch break nothing is deducted.');
        // Km of start, site and end only: 0 + 45 + 40.
        $this->assertSame(85, $days[self::MONDAY]['km']);
    }

    #[Test]
    public function absences_vacation_trainings_and_holidays_fill_themselves_in(): void
    {
        $user = User::factory()->create();
        VacationRequest::factory()->for($user)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'status' => VacationStatus::Approved]);
        Absence::factory()->for($user)->create(['type' => AbsenceType::Medical, 'start_date' => '2026-10-13', 'end_date' => '2026-10-13']);
        Absence::factory()->for($user)->ofType(AbsenceType::Family)->create(['start_date' => '2026-10-14', 'end_date' => '2026-10-14']);
        Absence::factory()->for($user)->ofType(AbsenceType::Overtime)->create(['start_date' => '2026-10-15', 'end_date' => '2026-10-15']);
        Training::factory()->create(['start_date' => '2026-10-16', 'end_date' => '2026-10-16', 'name' => 'BA5'])->participants()->attach($user);
        // Worked half a day while recuperating overtime: only those hours count.
        $rows = Timesheets::emptyRows();
        $rows[1] = [...$rows[1], 'arrival' => '08:00', 'departure' => '12:00'];
        $this->day($user, '2026-10-15', $rows, lunch: false);

        $week = app(Timesheets::class)->week($user, self::MONDAY);
        $days = collect($week['days'])->keyBy(fn (array $day): string => $day['date']->toDateString());

        $this->assertSame(['Verlof', 'Ziekte', 'Familiaal verlof', 'Recuperatie overuren', 'Opleiding: BA5 – Securex, Gent'], $days->map(fn (array $day): string => $day['status']['note'])->values()->all());
        $this->assertSame([8.0, 8.0, 0.0, 4.0, 8.0], $days->pluck('total')->values()->all());
        $this->assertSame(28.0, $week['hours']);
        $this->assertSame(-12.0, $week['overtime_now']);
        // Lunch is always on for vacation, medical leave and training.
        $this->assertSame([true, true, true, false, true], $days->pluck('lunch')->values()->all());
        $this->assertSame('ma 12/10: Verlof', $week['notes'][0]);

        Holiday::factory()->create(['date' => '2026-10-12', 'name' => 'Testdag']);
        $this->assertSame('Feestdag: Testdag', app(Timesheets::class)->week($user, self::MONDAY)['days'][0]['status']['note']);
    }

    #[Test]
    public function overtime_and_kilometres_carry_over_from_week_to_week(): void
    {
        $user = User::factory()->create();
        // Week 1: 5 × 8,5 h without lunch = 42,5 → +2,5; 5 × 85 km.
        foreach (range(0, 4) as $offset) {
            $this->day($user, CarbonImmutable::parse(self::MONDAY)->subWeek()->addDays($offset)->toDateString(), lunch: false);
        }
        TimesheetWeek::query()->create(['user_id' => $user->id, 'week_start' => CarbonImmutable::parse(self::MONDAY)->subWeek()->toDateString(), 'overtime_carried' => 1.5, 'km_carried' => 100]);

        $week = app(Timesheets::class)->week($user, self::MONDAY);

        $this->assertSame(4.0, $week['overtime_carried']);
        $this->assertSame(525, $week['km_carried']);
        $this->assertSame(-36.0, $week['overtime_new']);
    }

    #[Test]
    public function the_employee_fills_in_a_day_with_the_projects_of_the_planning(): void
    {
        Repeater::fake();
        $this->travelTo(self::MONDAY.' 17:00');
        $team = Team::factory()->create();
        $user = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create(['name' => 'Jeroen Lagaet']);
        Vehicle::factory()->create(['team_id' => $team->id, 'plate_number' => '2-CUR-747']);
        $project = Project::factory()->inCategory('604')->for(WorkSite::factory()->create(['cow_code' => '55MUN']))->create(['number' => 764, 'status' => ProjectStatus::InProgress]);
        $project->teams()->attach($team);
        PlanningItem::factory()->for($user)->forProject($project)->create(['date' => self::MONDAY]);
        $this->actingAs($user);

        $page = Livewire::test(Timesheet::class)
            ->assertSee('2-CUR-747')
            ->assertSee('Jeroen Lagaet')
            ->assertSee('12/10/26')
            ->assertSee('16/10/26');

        $this->assertSame('604-764', $page->get('data.rows')[array_keys($page->get('data.rows'))[1]]['imputation']);
        $this->assertSame('55MUN', $page->get('data.rows')[array_keys($page->get('data.rows'))[1]]['description']);

        $rows = $this->exampleRows();
        $rows[1]['project_id'] = $project->id;
        $page->set('data.rows', $rows)->set('data.remarks', 'Sleutel opgehaald')->call('save')->assertHasNoErrors();

        $day = TimesheetDay::sole();
        $this->assertSame('604-764', $day->rows[1]['imputation']);
        $this->assertSame('55MUN', $day->rows[1]['description']);
        $this->assertSame('2-CUR-747', TimesheetWeek::sole()->plate_number);
        $this->assertSame(8.0, app(Timesheets::class)->week($user, self::MONDAY)['days'][0]['total']);
    }

    #[Test]
    public function a_sent_week_is_locked_for_the_employee(): void
    {
        $user = User::factory()->inTeam()->create();
        TimesheetWeek::query()->create(['user_id' => $user->id, 'week_start' => self::MONDAY, 'sent_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Timesheet::class, ['date' => self::MONDAY])->call('save')->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['timesheets' => ['see_all', 'edit_all']])->inTeam()->create());
        Livewire::test(Timesheet::class, ['date' => self::MONDAY, 'employee' => $user->id])->call('save')->assertHasNoErrors();
        $this->assertSame(1, TimesheetDay::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function the_weekly_pdfs_are_mailed_to_the_recipients(): void
    {
        Mail::fake();
        settings()->save('timesheets', [...settings('timesheets'), 'recipients' => ['payroll@example.com', 'boss@example.com']]);
        $first = User::factory()->create(['name' => 'Anna']);
        $second = User::factory()->create(['name' => 'Bert']);
        $this->day($first, self::MONDAY);
        $this->day($second, self::MONDAY);

        $this->artisan('timesheets:send-weekly', ['--week' => self::MONDAY])->assertSuccessful();

        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::TimesheetWeek
            && $mail->hasTo('payroll@example.com') && $mail->hasTo('boss@example.com')
            && count($mail->files) === 2
            && str_contains($mail->rendered['html'], 'Anna'));
        $this->assertSame(2, TimesheetWeek::query()->whereNotNull('sent_at')->count());
        Storage::disk('local')->assertExists(TimesheetWeek::query()->where('user_id', $first->id)->value('pdf_path'));

        // Sent weeks are not sent again by the schedule.
        $this->artisan('timesheets:send-weekly', ['--week' => self::MONDAY])->assertSuccessful();
        Mail::assertSent(SystemMail::class, 1);

        settings()->save('timesheets', [...settings('timesheets'), 'recipients' => []]);
        $this->assertFalse(app(SendWeeklyTimesheets::class)->handle(CarbonImmutable::parse(self::MONDAY))['sent']);
    }

    #[Test]
    public function the_pdf_shows_the_week_like_the_excel(): void
    {
        $user = User::factory()->create(['name' => 'Jeroen Lagaet']);
        $this->day($user, self::MONDAY);

        $this->actingAs($user)->get(route('timesheets.pdf', ['user' => $user->id, 'week' => self::MONDAY]))->assertForbidden();

        $user->update(['role_id' => Role::factory()->create(['permissions' => ['timesheets' => ['generate_pdf']]])->id]);
        $user->teams()->attach(Team::factory()->create());
        $this->actingAs($user->fresh())->get(route('timesheets.pdf', ['user' => $user->id, 'week' => self::MONDAY]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $cells = app(TimesheetPdf::class)->cells($user, app(Timesheets::class)->week($user, self::MONDAY));
        $this->assertSame(['A' => 'Naam:', 'D' => 'Nr plaat:', 'F' => 'Week van:', 'G' => '12/10/26', 'H' => 'Week'], $cells[2]);
        $this->assertSame('42', $cells[3]['H']);
        $this->assertSame(['6,25', '6,50', '0,25'], [$cells[7]['F'], $cells[7]['G'], $cells[7]['H']]);
        $this->assertSame('12/10/26', $cells[10]['A']);
        $this->assertSame('maandag', $cells[11]['A']);
        $this->assertSame(['85', 'Middagpauze', 'Ja', '8,00'], [$cells[13]['D'], $cells[13]['F'], $cells[13]['G'], $cells[13]['H']]);

        // Colleagues cannot open someone else's sheet.
        $this->actingAs(User::factory()->inTeam()->create())->get(route('timesheets.pdf', ['user' => $user->id, 'week' => self::MONDAY]))->assertForbidden();
    }

    #[Test]
    public function only_the_right_people_plan_trainings_and_participants_cannot_be_planned(): void
    {
        $participant = User::factory()->create();
        $this->actingAs(User::factory()->withPermissions(['planning' => ['plan']])->inTeam()->create());
        $this->get('/trainings')->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['planning' => ['trainings']])->inTeam()->create());
        Livewire::test(ManageTrainings::class)
            ->callAction('create', [
                'name' => 'VCA VOL', 'start_date' => '2026-10-14', 'end_date' => '2026-10-15', 'participants' => [$participant->id],
                'company' => 'Securex', 'street' => 'Kortrijksesteenweg', 'house_number' => '12', 'addition' => 'B', 'postal_code' => '9000', 'city' => 'Gent',
                'contact_phone' => '09 123 45 67', 'contact_email' => 'info@example.com',
            ])
            ->assertHasNoActionErrors();

        $training = Training::sole();
        $this->assertSame('Kortrijksesteenweg 12 B, 9000 Gent', $training->addressLine());
        $this->assertSame(1, $participant->notifications()->count());
        $this->assertSame('Opleiding: VCA VOL – Securex, Gent', app(Planning::class)->blockedReason($participant, CarbonImmutable::parse('2026-10-15'), $participant));
        $this->assertSame(8.0, app(Timesheets::class)->week($participant, self::MONDAY)['days'][2]['total']);
    }

    #[Test]
    public function the_settings_tab_and_pages_work(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->fillForm(['timesheets.recipients' => ['Payroll@Example.com'], 'timesheets.send_day' => 6, 'timesheets.send_time' => '07:30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['payroll@example.com'], settings()->get('timesheets.recipients'));
        $this->assertSame(6, settings()->get('timesheets.send_day'));
        $this->assertSame('07:30', settings()->get('timesheets.send_time'));

        $this->get('/admin/timesheet-overview')->assertOk();
        $this->get('/timesheet')->assertOk();
        $this->get('/timesheet-overview')->assertOk();
        $this->actingAs(User::factory()->inTeam()->create())->get('/timesheet-overview')->assertForbidden();

        $this->setModules(['timesheets' => false]);
        $this->actingAs($admin)->get('/timesheet')->assertForbidden();
    }

    #[Test]
    public function employees_add_extra_sites_that_stay_before_the_end_of_the_day(): void
    {
        $this->travelTo(self::MONDAY.' 17:00');
        $user = User::factory()->inTeam()->create();
        $this->actingAs($user);

        $page = Livewire::test(Timesheet::class)
            ->callAction(TestAction::make('add')->schemaComponent('rows'));

        $rows = array_values($page->get('data.rows'));
        $this->assertCount(7, $rows);
        $this->assertSame(['start', 'site', 'extra', 'extra', 'extra', 'extra', 'end'], array_column($rows, 'kind'));

        // Five extra sites: none of them count, and the PDF keeps the six lines of the Excel.
        $state = $this->exampleRows();
        $extras = array_map(fn (int $number): array => [...$state[2], 'imputation' => '604-'.(100 + $number), 'description' => "0{$number}ABC"], range(1, 5));
        $page->set('data.rows', [$state[0], $state[1], ...$extras, $state[5]])->call('save')->assertHasNoErrors();

        $saved = Timesheets::normaliseRows(TimesheetDay::sole()->rows);
        $this->assertCount(8, $saved);
        $this->assertSame('end', $saved[7]['kind']);
        $this->assertSame(8.0, app(Timesheets::class)->week($user, self::MONDAY)['days'][0]['total']);

        $six = TimesheetPdf::sixRows($saved);
        $this->assertCount(6, $six);
        $this->assertSame('604-103 / 604-104 / 604-105', $six[4]['imputation']);
        $this->assertSame('03ABC / 04ABC / 05ABC', $six[4]['description']);
        $this->assertSame('end', $six[5]['kind']);
    }

    #[Test]
    public function the_monthly_overview_shows_everyone_like_the_excel(): void
    {
        $user = User::factory()->create(['name' => 'APRIL Joran']);
        $this->day($user, '2026-10-05');
        VacationRequest::factory()->for($user)->create(['start_date' => '2026-10-06', 'end_date' => '2026-10-07', 'status' => VacationStatus::Approved]);
        Absence::factory()->for($user)->create(['type' => AbsenceType::Medical, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);

        $report = app(TimesheetReport::class)->build(TimesheetReport::months(CarbonImmutable::parse('2026-10-01'), '1'));
        $row = collect($report['months'][0]['employees'])->firstWhere('user.id', $user->id);

        $this->assertSame(['8', 'V', 'V', 'Z'], [$row['days'][5]['value'], $row['days'][6]['value'], $row['days'][7]['value'], $row['days'][8]['value']]);
        $this->assertTrue($row['days'][3]['weekend']);
        $this->assertSame(85, $row['km']);
        $this->assertSame(16.4, $row['mobility'], '85 km × € 0,1929');
        $this->assertSame(['V' => 2.0, 'IR' => 0.0, 'Z' => 1.0, 'OP' => 0.0], $row['year']);
        $this->assertSame(8.0, $row['hours']);
        $this->assertSame(32.0, $row['total']);

        $this->assertCount(2, TimesheetReport::months(CarbonImmutable::parse('2026-12-01'), '2'));
        $this->assertSame('2027-01', TimesheetReport::months(CarbonImmutable::parse('2026-12-01'), '2')[1]->format('Y-m'));
        $this->assertSame('2026-01', TimesheetReport::months(CarbonImmutable::parse('2026-10-01'), 'year')[0]->format('Y-m'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/timesheet-report?month=2026-10')->assertOk()->assertSee('APRIL Joran')->assertSee('OKTOBER 2026');

        foreach (['1', '2', 'year'] as $span) {
            $this->get(route('timesheets.report-pdf', ['month' => '2026-10', 'span' => $span]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->actingAs(User::factory()->inTeam()->create())->get(route('timesheets.report-pdf', ['month' => '2026-10']))->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['timesheets' => ['see_all']])->inTeam()->create())->get(route('timesheets.report-pdf', ['month' => '2026-10']))->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['timesheets' => ['see_all', 'generate_pdf']])->inTeam()->create())->get(route('timesheets.report-pdf', ['month' => '2026-10']))->assertOk();
    }
}
