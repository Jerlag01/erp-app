<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CustomDashboardWidget;
use App\Filament\Widgets\DashboardCountersWidget;
use App\Filament\Widgets\Schemas\DashboardWidgetForm;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Support\DashboardCounters;
use App\Support\DashboardLayout;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

/**
 * The dashboard, arranged by every user for themselves ("Dashboard aanpassen"): columns, blocks and
 * counters, limited to what they may see, and blocks they build themselves ("Blok toevoegen": a counter,
 * follow-up list or status overview on any list, as numbers or percentages).
 */
class Dashboard extends BaseDashboard
{
    protected function panelId(): string
    {
        return Filament::getCurrentPanel()?->getId() ?? 'app';
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $panel = $this->panelId();
        $classes = DashboardLayout::widgets($panel);

        $layout = DashboardLayout::forUser($this->user(), $panel);
        $hasCounters = array_intersect($layout['counters'], array_keys(DashboardCounters::available($this->user()))) !== [];

        $custom = DashboardWidget::query()->availableTo($this->user(), $panel)->get()->keyBy(fn (DashboardWidget $widget): string => $widget->key());

        return collect($layout['widgets'])
            ->reject(fn (string $key): bool => $key === 'counters' && ! $hasCounters)
            ->filter(fn (string $key): bool => isset($classes[$key]) || $custom->has($key))
            ->map(fn (string $key): string|WidgetConfiguration => match (true) {
                $custom->has($key) => CustomDashboardWidget::make(['widgetId' => $custom[$key]->id, 'span' => $custom[$key]->column_span]),
                $classes[$key] === DashboardCountersWidget::class => DashboardCountersWidget::make(['panel' => $panel]),
                default => $classes[$key],
            })
            ->values()
            ->all();
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => DashboardLayout::forUser($this->user(), $this->panelId())['columns']];
    }

    protected function getHeaderActions(): array
    {
        return [
            // A block of one's own: counter, follow-up list or status overview on any list one can open.
            Action::make('addBlock')
                ->label(__('erp.dashboard.builder.add'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->modalHeading(__('erp.dashboard.builder.add'))
                ->modalDescription(__('erp.dashboard.builder.add_help'))
                ->modalWidth(Width::FourExtraLarge)
                ->schema(fn (): array => DashboardWidgetForm::schema($this->panelId()))
                ->action(function (array $data): void {
                    $widget = DashboardWidget::query()->create([
                        ...DashboardWidgetForm::attributes($data, $this->user()->isAdmin()),
                        'user_id' => $this->user()->id,
                        'panel' => $this->panelId(),
                    ]);
                    DashboardLayout::append($this->user(), $this->panelId(), $widget->key());
                    Notification::make()->title(__('erp.dashboard.saved'))->success()->send();
                    $this->redirect(static::getUrl(panel: $this->panelId()));
                }),
            Action::make('customize')
                ->label(__('erp.dashboard.customize'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading(__('erp.dashboard.customize'))
                ->modalDescription(__('erp.dashboard.customize_help'))
                ->fillForm(function (): array {
                    $layout = DashboardLayout::forUser($this->user(), $this->panelId());

                    return [
                        'columns' => (string) $layout['columns'],
                        'widgets' => array_values(array_intersect($layout['widgets'], array_keys(DashboardLayout::availableWidgets($this->panelId(), $this->user())))),
                        'counters' => array_values(array_intersect($layout['counters'], array_keys(DashboardCounters::available($this->user())))),
                    ];
                })
                ->schema([
                    ToggleButtons::make('columns')
                        ->label(__('erp.dashboard.columns'))
                        ->options(['1' => '1', '2' => '2', '3' => '3'])
                        ->inline()
                        ->required(),
                    Repeater::make('widgets')
                        ->label(__('erp.dashboard.blocks'))
                        ->helperText(__('erp.dashboard.blocks_help'))
                        ->simple(Select::make('key')->options(fn (): array => DashboardLayout::availableWidgets($this->panelId(), $this->user()))->required()->distinct())
                        ->reorderable()
                        ->addActionLabel(__('erp.dashboard.add_block')),
                    Repeater::make('counters')
                        ->label(__('erp.dashboard.counters_label'))
                        ->helperText(__('erp.dashboard.counters_help'))
                        ->simple(Select::make('key')->options(fn (): array => DashboardCounters::available($this->user()))->required()->distinct())
                        ->reorderable()
                        ->addActionLabel(__('erp.dashboard.add_counter')),
                ])
                ->extraModalFooterActions([
                    Action::make('resetDashboard')
                        ->label(__('erp.dashboard.reset'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->cancelParentActions()
                        ->action(function (): void {
                            DashboardLayout::reset($this->user(), $this->panelId());
                            $this->redirect(static::getUrl(panel: $this->panelId()));
                        }),
                ])
                ->action(function (array $data): void {
                    DashboardLayout::save($this->user(), $this->panelId(), $data);
                    Notification::make()->title(__('erp.dashboard.saved'))->success()->send();
                    $this->redirect(static::getUrl(panel: $this->panelId()));
                }),
        ];
    }
}
