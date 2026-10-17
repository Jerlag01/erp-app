<?php

namespace App\Filament\Resources\BatteryDossiers\Pages;

use App\Actions\Batteries\SaveBatteryDossier;
use App\Filament\Forms\FloorPlanEditor;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Models\BatteryDossier;
use App\Models\BatteryDossierBattery;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @property BatteryDossier $record
 */
class EditBatteryDossier extends EditRecord
{
    protected static string $resource = BatteryDossierResource::class;

    /**
     * Largest floor plan image accepted from the editor.
     */
    public const MAX_PNG_BYTES = 6_000_000;

    public function getTitle(): string
    {
        return __('erp.batteries.dossier_title', ['reference' => $this->record->reference()]);
    }

    public function getSubheading(): ?string
    {
        if ($this->record->isFinished()) {
            return $this->record->description().' — '.__('erp.batteries.finish.finished_on', [
                'date' => $this->record->finished_at->format('d/m/Y H:i'),
                'user' => $this->record->finisher?->name ?? '—',
            ]);
        }

        return $this->record->description();
    }

    /**
     * Whether the signed-in user may still change the dossier (it is locked once finished).
     */
    public function canModify(): bool
    {
        return $this->record->isEditableBy(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(fn (): bool => ! $this->canModify());
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->visible(fn (): bool => $this->canModify());
    }

    protected function getHeaderActions(): array
    {
        $model = $this->record::class;
        $documents = array_keys($model::documents());

        return [
            ActionGroup::make([
                ...array_map(fn (string $document): Action => Action::make('pdf_'.$document)
                    ->label(__('erp.batteries.documents.'.$document))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (): bool => $document !== $model::PRICE_DOCUMENT || Gate::allows('viewPrices', $this->record))
                    ->url(fn (): string => route($model::documentRoute(), [$this->record, $document]), shouldOpenInNewTab: true), $documents),
                Action::make('pdf_all')
                    ->label(__('erp.batteries.documents.all'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->url(fn (): string => route($model::documentRoute(), [$this->record, 'all'])),
            ])
                ->label(__('erp.batteries.pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), $model::AREA))
                ->button()
                ->color('gray'),
            Action::make('finish')
                ->label(__('erp.batteries.finish.button'))
                ->icon(Heroicon::OutlinedFlag)
                ->color('success')
                ->visible(fn (): bool => ! $this->record->isFinished() && Gate::allows('update', $this->record))
                ->requiresConfirmation()
                ->modalHeading(__('erp.batteries.finish.heading'))
                ->modalDescription(__('erp.batteries.finish.help'))
                ->modalSubmitActionLabel(__('erp.batteries.finish.button'))
                ->action(function (): void {
                    abort_unless(Gate::allows('update', $this->record), 403);

                    // Finish what is on screen: save first (also the photos just uploaded).
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->record->refresh();

                    if (count((array) $this->record->photos) === 0) {
                        Notification::make()->title(__('erp.batteries.finish.photos_required'))->body(__('erp.batteries.finish.photos_required_help'))->danger()->persistent()->send();

                        return;
                    }

                    $this->record->finish(auth()->user());
                    $this->fillForm();
                    Notification::make()->title(__('erp.batteries.finish.done'))->body(__('erp.batteries.finish.done_help'))->success()->send();
                }),
            Action::make('reopen')
                ->label(__('erp.batteries.finish.reopen'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible(fn (): bool => $this->record->isFinished() && auth()->user()?->hasPermission($model::AREA.'.edit_finished'))
                ->requiresConfirmation()
                ->modalDescription(__('erp.batteries.finish.reopen_help'))
                ->action(function () use ($model): void {
                    abort_unless((bool) auth()->user()?->hasPermission($model::AREA.'.edit_finished'), 403);
                    $this->record->reopen();
                    Notification::make()->title(__('erp.batteries.finish.reopened'))->success()->send();
                }),
            DeleteAction::make()->visible(fn (): bool => $this->canModify()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->syncBatteries();

        $data['strings'] = $this->record->batteries->mapWithKeys(fn (BatteryDossierBattery $battery): array => [$battery->position => [
            ...$battery->only(['id_number', 'cable_type', 'cables_positive', 'cables_negative', 'cable_section', 'fuse_amperage', 'fuse_type']),
            'cable_length_m' => $battery->cable_length_m !== null ? (float) $battery->cable_length_m : null,
            'cell_voltages' => array_values((array) $battery->cell_voltages),
            'cell_densities' => array_values((array) $battery->cell_densities),
            // Enersys: float voltage and the block voltages while floating.
            'float_voltage' => $battery->float_voltage !== null ? (float) $battery->float_voltage : null,
            'floating_voltages' => array_values((array) $battery->floating_voltages),
        ]])->all();
        // The PNG is made again by the editor when the drawing changes; it is not sent to the browser.
        $data['floor_plan_png'] = null;
        $data['floor_plan'] = $this->record->floor_plan ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A finished dossier is locked, whatever is sent.
        abort_unless($this->canModify(), 403);

        $this->strings = (array) ($data['strings'] ?? []);

        // Input fields without the input permission and signed documents are left out.
        $data = app(SaveBatteryDossier::class)->allowedData($this->record, $data, auth()->user());

        $png = $data['floor_plan_png'] ?? null;
        $data['floor_plan'] = FloorPlanEditor::sanitize($data['floor_plan'] ?? []);

        unset($data['strings'], $data['floor_plan_png'], $data['project_id']);

        if ($data['floor_plan'] === []) {
            $this->deleteFloorPlanPng();
            $data['floor_plan_png'] = null;
        } elseif (filled($png)) {
            $data['floor_plan_png'] = $this->storeFloorPlanPng((string) $png);
        }

        return $data;
    }

    /** @var array<int|string, mixed> */
    protected array $strings = [];

    protected function afterSave(): void
    {
        app(SaveBatteryDossier::class)->saveBatteries($this->record, $this->strings, auth()->user());

        // Rows were added or removed when the number of batteries changed: show them.
        $this->fillForm();
    }

    protected function storeFloorPlanPng(string $dataUrl): ?string
    {
        $png = str_starts_with($dataUrl, 'data:image/png;base64,') && strlen($dataUrl) <= self::MAX_PNG_BYTES
            ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true)
            : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['data.floor_plan' => __('erp.batteries.errors.floor_plan')]);
        }

        $path = "batteries/{$this->record->id}/floor-plan.png";
        Storage::disk(BatteryDossier::DISK)->put($path, $png);

        return $path;
    }

    protected function deleteFloorPlanPng(): void
    {
        if (filled($this->record->floor_plan_png)) {
            Storage::disk(BatteryDossier::DISK)->delete($this->record->floor_plan_png);
        }
    }
}
