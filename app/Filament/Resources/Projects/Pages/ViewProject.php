<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Gpw\RequestGpw;
use App\Actions\Projects\CloseProject;
use App\Actions\Projects\CopyProject;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Asbuilts\AsbuiltResource;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Filament\Resources\EnersysDossiers\EnersysDossierResource;
use App\Filament\Resources\Projects\Actions\CloseInterventionAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Models\BatteryDossier;
use App\Models\EnersysDossier;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPoNumber;
use App\Support\Interventions;
use App\Support\PdfAccess;
use App\Support\ProjectProgress;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
     * Ticks or unticks a step of the progress bar (610–650 projects). Ticking "PO received" on a project
     * without PO number opens the window to add one.
     */
    public function toggleStep(int $step): void
    {
        $project = $this->getRecord();
        abort_unless(ProjectProgress::appliesTo($project) && Gate::allows('update', $project), 403);

        ProjectProgress::toggle($project, $step, auth()->user());

        if ($step === ProjectProgress::PO_STEP && $project->steps()->where('step', $step)->exists() && $project->poNumbers()->doesntExist() && $this->mayAddPoNumber($project)) {
            $this->mountAction('addPoNumber');
        }
    }

    /**
     * Adding a PO number at any moment, from the project page:
     * - 600 projects: administrators and "projects.intervention_po";
     * - 610–650 projects once the step "PO received" is ticked: those who may edit the project, and
     *   "projects.intervention_po".
     * Only on a project they see that is not archived (administrators also on archived ones).
     */
    protected function mayAddPoNumber(Project $project): bool
    {
        $user = auth()->user();

        if (! Gate::allows('view', $project) || ($project->isArchived() && ! $user->isAdmin())) {
            return false;
        }

        if ($project->category?->numbering->isIntervention()) {
            return ProjectForm::mayAddInterventionPo();
        }

        return ProjectProgress::appliesTo($project)
            && $project->steps()->where('step', ProjectProgress::PO_STEP)->exists()
            && (Gate::allows('update', $project) || ProjectForm::mayAddInterventionPo());
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
            Action::make('enersysDossier')
                ->label(__('erp.enersys.dossier_button'))
                ->icon(Heroicon::OutlinedBattery50)
                ->color('gray')
                ->visible(fn (Project $record): bool => modules()->enersys()
                    && $record->category?->code === settings()->get(EnersysDossier::CATEGORY_SETTING)
                    && (($dossier = EnersysDossier::query()->where('project_id', $record->id)->latest('id')->first()) ? Gate::allows('view', $dossier) : Gate::allows('create', EnersysDossier::class)))
                ->url(fn (Project $record): string => ($dossier = EnersysDossier::query()->where('project_id', $record->id)->latest('id')->first())
                    ? EnersysDossierResource::getUrl('edit', ['record' => $dossier])
                    : EnersysDossierResource::getUrl('create', ['project' => $record->id])),
            // 600 projects at any moment, 610–650 once "PO received" is ticked (see mayAddPoNumber()).
            Action::make('addPoNumber')
                ->label(__('erp.projects.add_po_number'))
                ->icon(Heroicon::OutlinedHashtag)
                ->color('gray')
                ->visible(fn (Project $record): bool => $this->mayAddPoNumber($record))
                ->authorize(fn (Project $record): bool => $this->mayAddPoNumber($record))
                ->modalDescription(fn (Project $record): string => $record->poNumbers->isEmpty()
                    ? __('erp.projects.po_numbers_help')
                    : __('erp.projects.po_numbers_current', ['numbers' => $record->poNumbers->pluck('number')->implode(', ')]))
                ->schema([
                    TextInput::make('number')
                        ->label(__('erp.projects.po_number'))
                        ->placeholder('4500123456')
                        ->required()
                        ->length(10)
                        ->regex(ProjectPoNumber::PATTERN)
                        ->validationMessages(['regex' => __('erp.projects.po_number_invalid')])
                        ->rule(fn (Project $record) => Rule::unique('project_po_numbers', 'number')->where('project_id', $record->id))
                        ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                ])
                ->action(function (Project $record, array $data): void {
                    $record->poNumbers()->create(['number' => $data['number']]);
                    $record->load('poNumbers');
                })
                ->successNotificationTitle(__('erp.projects.po_number_added')),
            Action::make('asbuilt')
                ->label(__('erp.asbuilt.singular'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->visible(fn (Project $record): bool => $record->asbuilt !== null && Gate::allows('view', $record->asbuilt))
                ->url(fn (Project $record): string => AsbuiltResource::getUrl('view', ['record' => $record->asbuilt])),
            Action::make('requestGpw')
                ->label(__('erp.gpw.request'))
                ->icon(Heroicon::OutlinedCalendarDateRange)
                ->color('primary')
                ->visible(fn (Project $record): bool => Gate::allows('requestGpw', $record))
                ->authorize(fn (Project $record): bool => Gate::allows('requestGpw', $record))
                ->modalHeading(__('erp.gpw.request_heading'))
                ->modalDescription(fn (Project $record): string => ($recipients = RequestGpw::recipientsText($record))
                    ? __('erp.gpw.request_help', ['recipients' => $recipients])
                    : __('erp.gpw.errors.no_contact'))
                ->modalSubmitActionLabel(__('erp.gpw.send'))
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('starts_on')->label(__('erp.gpw.starts_on'))->native(false)->displayFormat('d/m/Y')->minDate(today())->required()->live(),
                        DatePicker::make('ends_on')->label(__('erp.gpw.ends_on'))->native(false)->displayFormat('d/m/Y')->minDate(today())->afterOrEqual('starts_on')->required(),
                    ]),
                    Textarea::make('remarks')->label(__('erp.gpw.remarks'))->helperText(__('erp.gpw.remarks_help'))->rows(4)->maxLength(2000),
                ])
                ->action(function (Project $record, array $data, RequestGpw $requests): void {
                    $gpw = $requests->handle($record, auth()->user(), $data);

                    $requests->mailFailed
                        ? Notification::make()->title(__('erp.gpw.mail_failed'))->body(__('erp.gpw.mail_failed_body'))->warning()->persistent()->send()
                        : Notification::make()->title(__('erp.gpw.sent', ['email' => $gpw->contact_email]))->success()->send();
                }),
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
