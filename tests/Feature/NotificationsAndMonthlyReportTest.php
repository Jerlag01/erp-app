<?php

namespace Tests\Feature;

use App\Actions\NotifyAbsenceAdministrators;
use App\Actions\Projects\SendMonthlyProjectReport;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Mail\SystemMail;
use App\Models\Absence;
use App\Models\BatteryDossier;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationsAndMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_bell_notifications_are_stored_at_once_without_a_queue_worker(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Notification::make()->title('Lage voorraad')->sendToDatabase($user);

        Queue::assertNothingPushed();
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('Lage voorraad', $user->notifications()->sole()->data['title']);
    }

    #[Test]
    public function a_project_status_change_reaches_its_leaders_but_not_whoever_changed_it(): void
    {
        $leader = User::factory()->create();
        $changer = User::factory()->create();
        $project = Project::factory()->inCategory('610')->create(['status' => ProjectStatus::ToBeExecuted]);
        $project->leaders()->attach([$leader->id, $changer->id]);
        $this->actingAs($changer);

        $project->update(['status' => ProjectStatus::InProgress]);

        $this->assertSame(1, $leader->notifications()->count());
        $this->assertStringContainsString($project->reference, $leader->notifications()->sole()->data['title']);
        $this->assertSame(0, $changer->notifications()->count());
    }

    #[Test]
    public function a_finished_battery_dossier_tells_the_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->create();
        $project = Project::factory()->inCategory('604')->create(['status' => ProjectStatus::InProgress]);
        $dossier = BatteryDossier::factory()->create(['project_id' => $project->id, 'photos' => ['batteries/1/photos/a.jpg']]);

        $dossier->finish($technician);

        $titles = $admin->notifications()->get()->pluck('data.title')->all();
        $this->assertContains(__('erp.batteries.notifications.finished_title', ['project' => $project->reference]), $titles);
    }

    #[Test]
    public function a_registered_absence_reaches_the_others_who_manage_leave(): void
    {
        $admin = User::factory()->admin()->create();
        $registrar = User::factory()->admin()->create();
        $absence = Absence::factory()->create();
        $this->actingAs($registrar);

        app(NotifyAbsenceAdministrators::class)->handle($absence->load('user'), NotifyAbsenceAdministrators::REGISTERED);

        $this->assertSame(__('erp.absences.registered_title', ['name' => $absence->user->name]), $admin->notifications()->sole()->data['title']);
        $this->assertSame(0, $registrar->notifications()->count());
    }

    #[Test]
    public function the_monthly_report_lists_the_open_projects_and_the_overdue_interventions(): void
    {
        $this->travelTo('2026-12-01 07:00');
        Mail::fake();
        $admin = User::factory()->admin()->create(['locale' => 'nl']);
        $leader = User::factory()->create(['locale' => 'nl']);
        $other = User::factory()->create();
        $open = Project::factory()->inCategory('610')->for(WorkSite::factory()->create(['cow_code' => '02GAM']))->create(['status' => ProjectStatus::InProgress, 'short_description' => 'Nieuwe gelijkrichter']);
        $late = Project::factory()->inCategory('600')->for(WorkSite::factory()->create(['cow_code' => '03ABC']))->create(['status' => ProjectStatus::ToBeExecuted, 'work_order_number' => '777777', 'intervention_due_on' => '2026-11-20']);
        $closed = Project::factory()->inCategory('610')->create(['status' => ProjectStatus::Invoiced]);
        $open->leaders()->attach($leader);

        $this->artisan('projects:monthly-report')->assertSuccessful();

        // The administrator: every open project and the overdue intervention.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::MonthlyProjectReport && $mail->hasTo($admin->email)
            && str_contains($mail->rendered['html'], $open->reference) && str_contains($mail->rendered['html'], '02GAM') && str_contains($mail->rendered['html'], 'Nieuwe gelijkrichter')
            && str_contains($mail->rendered['html'], $late->reference) && str_contains($mail->rendered['html'], '20/11/2026')
            && ! str_contains($mail->rendered['html'], $closed->reference)
            && str_contains($mail->rendered['html'], '<table>')
            && str_contains($mail->rendered['subject'], 'November 2026'));

        // The project leader: only their own project.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo($leader->email) && str_contains($mail->rendered['html'], $open->reference) && ! str_contains($mail->rendered['html'], $late->reference));
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo($other->email));
        $this->assertSame(2, app(SendMonthlyProjectReport::class)->handle());
    }
}
