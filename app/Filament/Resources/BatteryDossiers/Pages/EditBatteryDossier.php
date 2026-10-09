<?php

namespace App\Filament\Resources\BatteryDossiers\Pages;

use App\Actions\Batteries\BatteryDocument;
use App\Filament\Forms\FloorPlanEditor;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Models\BatteryDossier;
use App\Models\BatteryDossierBattery;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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
        return $this->record->description();
    }

    protected function getHeaderActions(): array
    {
        $documents = array_keys(BatteryDocument::DOCUMENTS);

        return [
            ActionGroup::make([
                ...array_map(fn (string $document): Action => Action::make('pdf_'.$document)
                    ->label(__('erp.batteries.documents.'.$document))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (): bool => $document !== 'invoice_request' || Gate::allows('viewPrices', $this->record))
                    ->url(fn (): string => route('batteries.document', [$this->record, $document]), shouldOpenInNewTab: true), $documents),
                Action::make('pdf_all')
                    ->label(__('erp.batteries.documents.all'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->url(fn (): string => route('batteries.document', [$this->record, 'all'])),
            ])
                ->label(__('erp.batteries.pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->button()
                ->color('gray'),
            DeleteAction::make(),
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
        $this->strings = (array) ($data['strings'] ?? []);
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
        $this->record->syncBatteries();
        $cells = max(1, (int) $this->record->cells_per_battery);
        $number = fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;

        foreach ($this->record->batteries as $battery) {
            $row = (array) ($this->strings[$battery->position] ?? []);

            if ($row === []) {
                continue;
            }

            $battery->update([
                'id_number' => filled($row['id_number'] ?? null) ? trim((string) $row['id_number']) : $battery->id_number,
                'cable_type' => $row['cable_type'] ?? null,
                'cable_length_m' => $number($row['cable_length_m'] ?? null),
                'cables_positive' => is_numeric($row['cables_positive'] ?? null) ? (int) $row['cables_positive'] : null,
                'cables_negative' => is_numeric($row['cables_negative'] ?? null) ? (int) $row['cables_negative'] : null,
                'cable_section' => $row['cable_section'] ?? null,
                'fuse_amperage' => $row['fuse_amperage'] ?? null,
                'fuse_type' => filled($row['fuse_type'] ?? null) ? $row['fuse_type'] : BatteryDossierBattery::DEFAULT_FUSE_TYPE,
                'cell_voltages' => array_map(fn (int $index): ?float => $number(((array) ($row['cell_voltages'] ?? []))[$index] ?? null), range(0, $cells - 1)),
                'cell_densities' => array_map(fn (int $index): ?float => $number(((array) ($row['cell_densities'] ?? []))[$index] ?? null), range(0, $cells - 1)),
            ]);
        }

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
