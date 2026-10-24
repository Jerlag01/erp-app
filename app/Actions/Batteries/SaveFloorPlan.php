<?php

namespace App\Actions\Batteries;

use App\Filament\Forms\FloorPlanEditor;
use App\Models\BatteryDossier;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The floor plan of a battery dossier: the drawing as shapes (cleaned) and the PNG the editor made of
 * it for the PDF. Used by the dossier page and the mobile app, which use the same editor.
 */
class SaveFloorPlan
{
    public const MAX_PNG_BYTES = 6_000_000;

    /**
     * The values to store on the dossier: the cleaned shapes and the path of the PNG (null without a
     * drawing; unchanged when no new PNG came with the shapes).
     *
     * @return array{floor_plan: list<array<string, mixed>>, floor_plan_png?: string|null}
     */
    public function values(BatteryDossier $dossier, mixed $shapes, ?string $pngDataUrl): array
    {
        $values = ['floor_plan' => FloorPlanEditor::sanitize($shapes ?? [])];

        if ($values['floor_plan'] === []) {
            $this->deletePng($dossier);
            $values['floor_plan_png'] = null;
        } elseif (filled($pngDataUrl)) {
            $values['floor_plan_png'] = $this->storePng($dossier, (string) $pngDataUrl);
        }

        return $values;
    }

    /**
     * Saves the drawing on the dossier.
     */
    public function handle(BatteryDossier $dossier, mixed $shapes, ?string $pngDataUrl): void
    {
        $dossier->update($this->values($dossier, $shapes, $pngDataUrl));
    }

    protected function storePng(BatteryDossier $dossier, string $dataUrl): string
    {
        $png = str_starts_with($dataUrl, 'data:image/png;base64,') && strlen($dataUrl) <= self::MAX_PNG_BYTES
            ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true)
            : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['data.floor_plan' => __('erp.batteries.errors.floor_plan')]);
        }

        $path = "batteries/{$dossier->id}/floor-plan.png";
        Storage::disk(BatteryDossier::DISK)->put($path, $png);

        return $path;
    }

    protected function deletePng(BatteryDossier $dossier): void
    {
        if (filled($dossier->floor_plan_png)) {
            Storage::disk(BatteryDossier::DISK)->delete($dossier->floor_plan_png);
        }
    }
}
