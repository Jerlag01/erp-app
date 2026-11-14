<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CustomDashboardWidget;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\User;
use App\Support\Dashboard\DashboardSource;
use App\Support\Dashboard\DashboardWidgetData;
use App\Support\DashboardLayout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dashboard blocks people build themselves: counters, follow-up lists and status overviews on any list,
 * with filters, as numbers or percentages.
 */
class DashboardBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        return $admin;
    }

    protected function projects(): void
    {
        Project::factory()->count(3)->create(['status' => ProjectStatus::InProgress]);
        Project::factory()->create(['status' => ProjectStatus::Planned]);
        Project::factory()->create(['status' => ProjectStatus::InvoicingToDo, 'estimated_workdays' => 4]);
    }

    #[Test]
    public function every_list_the_user_may_open_is_a_source_with_its_fields(): void
    {
        $admin = $this->admin();
        $source = DashboardSource::find('app', 'projects');

        $this->assertNotNull($source);
        $fields = $source->fields();
        $this->assertSame('enum', $fields['status']['type']);
        $this->assertSame('relation', $fields['work_site_id']['type']);
        $this->assertSame('date', $fields['created_at']['type']);
        $this->assertArrayHasKey('in_progress', $source->valueOptions('status'));
        $this->assertArrayHasKey('me', $source->valueOptions('created_by'));

        // Secrets never show (users in the admin panel).
        Filament::setCurrentPanel('admin');
        $users = DashboardSource::find('admin', 'users');
        $this->assertNotNull($users);
        $this->assertEmpty(array_filter(array_keys($users->fields($admin)), fn (string $key): bool => (bool) preg_match('/password|token|secret/', $key)));

        // An employee without access to projects has no such source.
        $employee = User::factory()->withPermissions(['vehicles' => ['view']])->inTeam()->create();
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');
        $this->assertNull(DashboardSource::find('app', 'projects'));
    }

    #[Test]
    public function counters_lists_and_status_overviews_follow_their_filters_as_numbers_or_percentages(): void
    {
        $admin = $this->admin();
        $this->projects();

        // Counter: 3 of 5 projects in progress, 60 %.
        $counter = DashboardWidget::factory()->for($admin)->create(['type' => DashboardWidget::COUNTER, 'source' => 'projects', 'config' => [
            'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['in_progress']]],
            'aggregate' => 'count', 'display' => 'both',
        ]]);
        $data = DashboardWidgetData::for($counter);
        $this->assertSame([3.0, 5.0, 60.0], [$data['value'], $data['base'], $data['percentage']]);

        // Percentage of the open ones only (not to invoice), and a sum of a number field.
        $counter->update(['config' => [...$counter->config, 'base_filters' => [['field' => 'status', 'operator' => 'not_in', 'value' => ['invoicing_to_do']]]]]);
        $this->assertSame(75.0, DashboardWidgetData::for($counter->fresh())['percentage']);
        $sum = DashboardWidget::factory()->for($admin)->create(['config' => ['filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['invoicing_to_do']]], 'aggregate' => 'sum', 'aggregate_field' => 'estimated_workdays', 'display' => 'number']]);
        $this->assertSame(4.0, DashboardWidgetData::for($sum)['value']);

        // Follow-up list: the projects to invoice, with their status, linking to the project.
        $list = DashboardWidget::factory()->for($admin)->create(['type' => DashboardWidget::LIST, 'config' => [
            'filters' => [['field' => 'status', 'operator' => 'not_in', 'value' => ['in_progress']]],
            'columns' => ['status', 'estimated_workdays'], 'sort_field' => 'created_at', 'sort_direction' => 'desc', 'limit' => 1,
        ]]);
        $data = DashboardWidgetData::for($list);
        $this->assertSame(2, $data['total']);
        $this->assertCount(1, $data['rows']);
        $this->assertContains($data['rows'][0]['values'][0], [ProjectStatus::Planned->getLabel(), ProjectStatus::InvoicingToDo->getLabel()]);
        $this->assertNotNull($data['rows'][0]['url']);

        // Status overview: per status, the largest first, with percentages of the total.
        $status = DashboardWidget::factory()->for($admin)->create(['type' => DashboardWidget::STATUS, 'config' => ['group_by' => 'status', 'aggregate' => 'count', 'display' => 'percentage']]);
        $data = DashboardWidgetData::for($status);
        $this->assertSame(5.0, $data['total']);
        $this->assertSame([ProjectStatus::InProgress->getLabel(), 3.0, 60.0], [$data['groups'][0]['label'], $data['groups'][0]['value'], $data['groups'][0]['percentage']]);
        $this->assertCount(3, $data['groups']);

        // By month of creation.
        $months = DashboardWidget::factory()->for($admin)->create(['type' => DashboardWidget::STATUS, 'config' => ['group_by' => 'created_at']]);
        $this->assertSame(5.0, DashboardWidgetData::for($months)['groups'][0]['value']);
    }

    #[Test]
    public function blocks_are_built_on_the_dashboard_edited_shared_and_removed(): void
    {
        $admin = $this->admin();
        $this->projects();

        Livewire::test(Dashboard::class)
            ->callAction('addBlock', [
                'title' => 'In uitvoering',
                'source' => 'projects',
                'type' => DashboardWidget::COUNTER,
                'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['in_progress']]],
                'aggregate' => 'count',
                'display' => 'percentage',
                'color' => 'success',
                'column_span' => '2',
                'is_shared' => true,
            ])
            ->assertHasNoActionErrors();

        $widget = DashboardWidget::query()->sole();
        $this->assertSame(['In uitvoering', 'projects', 'percentage', true, '2'], [$widget->title, $widget->source, $widget->setting('display'), $widget->is_shared, $widget->column_span]);
        $this->assertContains($widget->key(), DashboardLayout::forUser($admin->fresh(), 'app')['widgets']);

        $page = Livewire::test(Dashboard::class)->instance();
        $this->assertContains(CustomDashboardWidget::class, collect($page->getWidgets())->map(fn ($widget) => is_string($widget) ? $widget : $widget->widget)->all());
        Livewire::test(CustomDashboardWidget::class, ['widgetId' => $widget->id, 'span' => '2'])
            ->assertSee('In uitvoering')
            ->assertSee('60 %')
            ->callAction('edit', ['title' => 'Lopend', 'source' => 'projects', 'type' => DashboardWidget::COUNTER, 'aggregate' => 'count', 'display' => 'number', 'column_span' => '1', 'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['in_progress']]]]);
        $this->assertSame(['Lopend', 'number'], [$widget->fresh()->title, $widget->fresh()->setting('display')]);

        // Shared: another user can add it and sees only their own data, but cannot change it.
        $leader = User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create();
        $this->actingAs($leader);
        $this->assertArrayHasKey($widget->key(), DashboardLayout::availableWidgets('app', $leader));
        Livewire::test(CustomDashboardWidget::class, ['widgetId' => $widget->id])
            ->assertSee('Lopend')
            ->assertSee('0')
            ->assertActionHidden('edit')
            ->assertActionHidden('delete');

        // Someone without access to the list sees why the block is empty.
        $this->actingAs(User::factory()->withPermissions(['vehicles' => ['view']])->inTeam()->create());
        Livewire::test(CustomDashboardWidget::class, ['widgetId' => $widget->id])->assertSee(__('erp.dashboard.builder.errors.no_access'));

        $this->actingAs($admin);
        Livewire::test(CustomDashboardWidget::class, ['widgetId' => $widget->id])->callAction('delete');
        $this->assertSame(0, DashboardWidget::query()->count());
        $this->assertNotContains($widget->key(), DashboardLayout::forUser($admin->fresh(), 'app')['widgets']);
    }
}
