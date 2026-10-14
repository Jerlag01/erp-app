<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ControlsDue;
use App\Filament\Widgets\DashboardCountersWidget;
use App\Models\Employee;
use App\Models\EmployeeMedicalCheck;
use App\Models\User;
use App\Support\DashboardCounters;
use App\Support\DashboardLayout;
use App\Support\TrainingMatrix;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_language_is_switched_from_the_top_bar(): void
    {
        $user = User::factory()->inTeam()->create(['locale' => 'nl']);
        $this->actingAs($user);

        $this->get('/')->assertOk()->assertSee(route('locale.update'), false)->assertSee('Français');

        $this->from('/')->post(route('locale.update'), ['locale' => 'fr'])->assertRedirect('/');
        $this->assertSame('fr', $user->fresh()->locale);

        $this->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->assertSame('fr', $user->fresh()->locale);
    }

    #[Test]
    public function the_training_overview_shows_validity_like_the_excel(): void
    {
        $this->travelTo('2026-10-14');
        $user = User::factory()->create(['job_title' => 'Elektrieker']);
        $employee = Employee::factory()->for($user)->create(['first_name' => 'Jeroen', 'last_name' => 'Lagaet', 'is_mentor' => true]);
        $employee->certificates()->createMany([
            ['type' => CertificateType::VcaVol, 'expires_on' => '2034-01-25'],
            ['type' => CertificateType::Nuclear, 'expires_on' => '2026-02-18'],
            ['type' => CertificateType::Proximus, 'expires_on' => '2026-12-01'],
            ['type' => CertificateType::Ba4, 'obtained_on' => '2024-11-19'],
            ['type' => CertificateType::SchoolDegree, 'notes' => 'Bachelor'],
        ]);
        EmployeeMedicalCheck::query()->create(['employee_id' => $employee->id, 'checked_on' => '2026-01-10', 'result' => 'fit']);
        Employee::factory()->create(['first_name' => 'Andy', 'last_name' => 'Thys']);

        $matrix = app(TrainingMatrix::class)->build();
        $row = collect($matrix['rows'])->firstWhere('name', 'Jeroen Lagaet');

        $this->assertSame(['valid', 'expired', 'soon', 'since'], [$row['cells']['vca_vol']['state'], $row['cells']['nuclear']['state'], $row['cells']['proximus']['state'], $row['cells']['ba4']['state']]);
        $this->assertSame('19/11/2024', $row['cells']['ba4']['text']);
        $this->assertSame('10/01/2027', $row['cells']['medical']['text']);
        $this->assertTrue($row['mentor']);
        $this->assertSame('Elektrieker', $row['function']);
        $this->assertNotContains('school_degree', array_column($matrix['columns'], 'key'));
        $this->assertSame(2, $matrix['expired'], 'The nuclear certificate and the missing check-up of Andy.');
        $this->assertSame(2, $matrix['soon'], 'Proximus and the next check-up (10/01/2027) fall within three months.');

        $this->assertCount(2, app(TrainingMatrix::class)->build(onlyProblems: true)['rows']);
        $this->assertCount(1, app(TrainingMatrix::class)->build(search: 'elektr')['rows']);

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create())->get('/admin/training-overview')->assertOk()->assertSee('Jeroen Lagaet')->assertSee('18/02/2026');
        $this->actingAs(User::factory()->withPermissions([])->inTeam()->create())->get('/admin/training-overview')->assertForbidden();
    }

    #[Test]
    public function employees_arrange_their_dashboard_with_what_they_may_see(): void
    {
        Filament::setCurrentPanel('app');
        $user = User::factory()->withPermissions(['vehicles' => ['view'], 'projects' => ['view']])->inTeam()->create();
        $this->actingAs($user);

        $widgets = DashboardLayout::availableWidgets('app');
        $this->assertArrayHasKey('controls_due', $widgets);
        $this->assertArrayNotHasKey('low_stock', $widgets, 'No stock rights.');
        $counters = DashboardCounters::available($user);
        $this->assertArrayHasKey('open_projects', $counters);
        $this->assertArrayNotHasKey('employee_requests', $counters, 'Administrators only.');

        // Blocks and counters without the rights are refused.
        Livewire::test(Dashboard::class)
            ->callAction('customize', [
                'columns' => '1',
                'widgets' => [['key' => 'controls_due'], ['key' => 'low_stock']],
                'counters' => [['key' => 'employee_requests']],
            ])
            ->assertHasActionErrors(['widgets.1.key', 'counters.0.key']);

        Livewire::test(Dashboard::class)
            ->callAction('customize', [
                'columns' => '1',
                'widgets' => [['key' => 'controls_due'], ['key' => 'counters']],
                'counters' => [['key' => 'open_projects']],
            ])
            ->assertHasNoActionErrors();

        $layout = DashboardLayout::forUser($user->fresh(), 'app');
        $this->assertSame([1, ['controls_due', 'counters'], ['open_projects']], [$layout['columns'], $layout['widgets'], $layout['counters']]);

        // Saved directly, unknown or forbidden entries are dropped as well.
        DashboardLayout::save($user->fresh(), 'app', ['columns' => 9, 'widgets' => ['controls_due', 'low_stock'], 'counters' => ['open_projects', 'employee_requests']]);
        $layout = DashboardLayout::forUser($user->fresh(), 'app');
        $this->assertSame([3, ['controls_due'], ['open_projects']], [$layout['columns'], $layout['widgets'], $layout['counters']]);
        DashboardLayout::save($user->fresh(), 'app', ['columns' => 1, 'widgets' => ['controls_due', 'counters'], 'counters' => ['open_projects']]);

        $page = Livewire::test(Dashboard::class)->instance();
        $this->assertSame(ControlsDue::class, $page->getWidgets()[0]);
        $this->get('/')->assertOk();

        Livewire::test(DashboardCountersWidget::class, ['panel' => 'app'])->assertSee(__('erp.dashboard.counters.open_projects'));

        // Back to the standard dashboard.
        DashboardLayout::reset($user->fresh(), 'app');
        $this->assertFalse(DashboardLayout::forUser($user->fresh(), 'app')['custom']);
    }

    #[Test]
    public function administrators_arrange_the_admin_dashboard_separately(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertArrayHasKey('employee_requests', DashboardCounters::available($admin));

        Livewire::test(Dashboard::class)
            ->callAction('customize', ['columns' => '3', 'widgets' => [['key' => 'counters'], ['key' => 'stats']], 'counters' => [['key' => 'employee_requests'], ['key' => 'certificates_expiring']]])
            ->assertHasNoActionErrors();

        $this->assertSame(3, DashboardLayout::forUser($admin->fresh(), 'admin')['columns']);
        $this->assertFalse(DashboardLayout::forUser($admin->fresh(), 'app')['custom']);
        $this->get('/admin')->assertOk();
    }
}
