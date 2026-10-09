<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\Actions\CloseInterventionAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Support\Interventions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * Employees in the planning for a project open it from their planning without access to the
     * project list ("projects.view"), so only the record itself is checked (ProjectPolicy::view).
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(modules()->projects(), 403);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->subtitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            CloseInterventionAction::make(),
            Action::make('interventionPdf')
                ->label(__('erp.interventions.pdf_button'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (Project $record): ?string => $record->interventionReport ? route('interventions.pdf', $record->interventionReport) : null, shouldOpenInNewTab: true)
                ->visible(fn (Project $record): bool => $record->interventionReport !== null && app(Interventions::class)->canDownloadPdf(auth()->user(), $record)),
            Action::make('changeStatus')
                ->label(__('erp.projects.change_status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Project $record): bool => Gate::allows('update', $record))
                ->authorize(fn (Project $record): bool => Gate::allows('update', $record))
                ->fillForm(fn (Project $record): array => ['status' => $record->status])
                ->schema([
                    ToggleButtons::make('status')
                        ->hiddenLabel()
                        ->options(ProjectStatus::class)
                        ->required(),
                ])
                ->action(function (Project $record, array $data): void {
                    $record->update(['status' => $data['status']]);
                    $record->refresh();
                })
                ->successNotificationTitle(__('erp.projects.status_changed')),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
