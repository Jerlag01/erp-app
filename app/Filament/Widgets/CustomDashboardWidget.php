<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Schemas\DashboardWidgetForm;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Support\Dashboard\DashboardWidgetData;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * A dashboard block someone built (see DashboardWidget and DashboardWidgetData): a counter, a follow-up
 * list or a status overview. Its owner (or an administrator for a shared block) edits or removes it here.
 */
class CustomDashboardWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.custom-dashboard-widget';

    public int $widgetId = 0;

    public string $span = '1';

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }

    public function getColumnSpan(): int|string|array
    {
        return match ($this->span) {
            'full' => 'full',
            '2' => ['default' => 1, 'md' => 2],
            default => 1,
        };
    }

    public function record(): ?DashboardWidget
    {
        /** @var User $user */
        $user = auth()->user();

        return DashboardWidget::query()->availableTo($user, $this->panel())->find($this->widgetId);
    }

    protected function panel(): string
    {
        return filament()->getCurrentPanel()?->getId() ?? 'app';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $widget = $this->record();

        return $widget ? DashboardWidgetData::for($widget) : ['error' => 'missing'];
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('erp.dashboard.builder.edit'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => (bool) $this->record()?->isEditableBy(auth()->user()))
            ->modalHeading(__('erp.dashboard.builder.edit'))
            ->modalWidth(Width::FourExtraLarge)
            ->fillForm(fn (): array => DashboardWidgetForm::fill($this->record()))
            ->schema(fn (): array => DashboardWidgetForm::schema($this->panel()))
            ->action(function (array $data): void {
                $widget = $this->record();
                abort_unless($widget?->isEditableBy(auth()->user()), 403);
                $widget->update(DashboardWidgetForm::attributes($data, (bool) auth()->user()?->isAdmin()));
                $this->span = $widget->column_span;
                Notification::make()->title(__('erp.dashboard.saved'))->success()->send();
                $this->redirect(Dashboard::getUrl(panel: $this->panel()));
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('erp.dashboard.builder.delete'))
            ->iconButton()
            ->icon(Heroicon::OutlinedTrash)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => (bool) $this->record()?->isEditableBy(auth()->user()))
            ->requiresConfirmation()
            ->modalDescription(__('erp.dashboard.builder.delete_help'))
            ->action(function (): void {
                $widget = $this->record();
                abort_unless($widget?->isEditableBy(auth()->user()), 403);
                $widget->delete();
                $this->redirect(Dashboard::getUrl(panel: $this->panel()));
            });
    }
}
