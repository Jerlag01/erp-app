<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
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
