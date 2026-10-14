<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\CloseProject;
use App\Actions\Projects\CopyProject;
use App\Enums\ProjectStatus;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Filament\Resources\Projects\Actions\CloseInterventionAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BatteryDossier;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Support\Interventions;
use App\Support\PdfAccess;
use App\Support\ProjectProgress;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
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

    protected function batteryDossier(Project $project): ?BatteryDossier
    {
        return BatteryDossier::query()->where('project_id', $project->id)->latest('id')->first();
    }

    /**
     * Ticks or unticks a step of the progress bar (610–650 projects).
     */
    public function toggleStep(int $step): void
    {
        $project = $this->getRecord();
        abort_unless(ProjectProgress::appliesTo($project) && Gate::allows('update', $project), 403);

        ProjectProgress::toggle($project, $step, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('erp.projects.archive.download'))
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('gray')
                ->visible(fn (Project $record): bool => Gate::allows('downloadArchive', $record))
                ->url(fn (Project $record): string => route('projects.archive', $record)),
            Action::make('reopen')
                ->label(__('erp.projects.archive.reopen'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible(fn (Project $record): bool => $record->isArchived() && (bool) auth()->user()?->isAdmin())
                ->modalDescription(__('erp.projects.archive.reopen_help'))
                ->schema([
                    ToggleButtons::make('status')
                        ->label(__('erp.fields.status'))
                        ->options(collect(ProjectStatus::cases())->reject(fn (ProjectStatus $status): bool => in_array($status, [ProjectStatus::Closed, ProjectStatus::Invoiced], true))->mapWithKeys(fn (ProjectStatus $status): array => [$status->value => $status->getLabel()])->all())
                        ->default(ProjectStatus::InvoicingToDo->value)
                        ->inline()
                        ->required(),
                ])
                ->action(function (Project $record, array $data): void {
                    abort_unless((bool) auth()->user()?->isAdmin(), 403);
                    app(CloseProject::class)->reopen($record, ProjectStatus::from($data['status']));
                    $record->refresh();
                })
                ->successNotificationTitle(__('erp.projects.archive.reopened')),
            Action::make('copy')
                ->label(__('erp.projects.copy.button'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->visible(fn (Project $record): bool => Gate::allows('copy', $record))
                ->authorize(fn (Project $record): bool => Gate::allows('copy', $record))
                ->modalHeading(__('erp.projects.copy.heading'))
                ->modalDescription(__('erp.projects.copy.help'))
                ->fillForm(fn (Project $record): array => [
                    'project_category_id' => $record->project_category_id,
                    'work_site_id' => $record->work_site_id,
                    'short_description' => $record->short_description,
                    'with_files' => false,
                ])
                ->schema([
                    Select::make('project_category_id')
                        ->label(__('erp.resources.project_category.singular'))
                        ->options(fn (): array => ProjectCategory::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (ProjectCategory $category): array => [$category->id => $category->label()])->all())
                        ->required(),
                    Select::make('work_site_id')
                        ->label(__('erp.resources.work_site.singular'))
                        ->relationship('workSite', 'cow_code')
                        ->searchable(),
                    TextInput::make('short_description')->label(__('erp.projects.short_description'))->maxLength(255),
                    Toggle::make('with_files')->label(__('erp.projects.copy.with_files')),
                ])
                ->action(function (Project $record, array $data, CopyProject $copy): void {
                    $new = $copy->handle($record, auth()->user(), $data);
                    Notification::make()->title(__('erp.projects.copy.done', ['reference' => $new->reference]))->success()->send();
                    $this->redirect(ProjectResource::getUrl('view', ['record' => $new]));
                }),
            CloseInterventionAction::make(),
            Action::make('interventionPdf')
                ->label(__('erp.interventions.pdf_button'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (Project $record): ?string => $record->interventionReport ? route('interventions.pdf', $record->interventionReport) : null, shouldOpenInNewTab: true)
                ->visible(fn (Project $record): bool => $record->interventionReport !== null && app(Interventions::class)->canDownloadPdf(auth()->user(), $record) && PdfAccess::canGenerate(auth()->user(), 'projects')),
            Action::make('batteryDossier')
                ->label(__('erp.batteries.dossier_button'))
                ->icon(Heroicon::OutlinedBattery100)
                ->color('gray')
                ->visible(fn (Project $record): bool => modules()->batteries()
                    && $record->category?->code === settings()->get('batteries.project_category')
                    && ($this->batteryDossier($record) ? Gate::allows('view', $this->batteryDossier($record)) : Gate::allows('create', BatteryDossier::class)))
                ->url(fn (Project $record): string => ($dossier = $this->batteryDossier($record))
                    ? BatteryDossierResource::getUrl('edit', ['record' => $dossier])
                    : BatteryDossierResource::getUrl('create', ['project' => $record->id])),
            Action::make('changeStatus')
                ->label(__('erp.projects.change_status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Project $record): bool => Gate::allows('update', $record))
                ->authorize(fn (Project $record): bool => Gate::allows('update', $record))
                ->fillForm(fn (Project $record): array => ['status' => $record->status])
                ->modalDescription(__('erp.projects.status_closed_help'))
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
