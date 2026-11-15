<?php

namespace App\Support;

use App\Enums\AsbuiltCategory;
use App\Enums\ProjectFileSection;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\CableCalculation;
use App\Models\ProjectFile;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The PDF of a cable calculation, and where it is kept for its project: in the project's extra files (a
 * new version on every save) and in the "cable dimensions" of the project's as-built while that is open.
 * Moving the calculation to another project, or unlinking it, removes its files from the former project.
 */
class CableCalculationDocuments
{
    public function render(CableCalculation $calculation): string
    {
        $calculation->loadMissing(['project', 'creator', 'circuits']);

        return Pdf::loadView('cable-calculations.pdf', [
            'calculation' => $calculation,
            'background' => BatteryTemplates::backgroundDataUri(),
        ])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true)->output();
    }

    public function fileName(CableCalculation $calculation): string
    {
        return Str::slug(__('erp.cable_calculator.singular').' '.$calculation->name).'.pdf';
    }

    /**
     * Brings the project's copies up to date with the calculation as it is now (after its circuits are saved).
     */
    public function sync(CableCalculation $calculation, ?User $user = null): void
    {
        $calculation->unsetRelation('circuits')->load(['project.asbuilt', 'projectFile', 'asbuiltFile.asbuilt']);
        $project = $calculation->project;

        $file = $calculation->projectFile;
        if ($file !== null && $file->project_id !== $project?->id) {
            $file->delete();
            $file = null;
        }

        $asbuiltFile = $calculation->asbuiltFile;
        if ($asbuiltFile !== null && $asbuiltFile->asbuilt?->project_id !== $project?->id) {
            $this->removeFromAsbuilt($asbuiltFile);
            $asbuiltFile = null;
        }

        if ($project !== null) {
            $pdf = $this->render($calculation);
            $name = $this->fileName($calculation);

            $path = ProjectFile::directoryFor($project, ProjectFileSection::Extra).'/'.Str::random(8).'-'.$name;
            Storage::disk(ProjectFile::DISK)->put($path, $pdf);
            $file = ProjectFile::store($project, ProjectFileSection::Extra, $path, $name, $user, $file);

            $asbuiltFile = $this->putInAsbuilt($project->asbuilt, $asbuiltFile, $pdf, $name, $user);
        }

        $calculation->forceFill(['project_file_id' => $file?->id, 'asbuilt_file_id' => $asbuiltFile?->id])->saveQuietly();
    }

    /**
     * Adds the PDF of every calculation of the project to a new as-built.
     */
    public function attachToAsbuilt(Asbuilt $asbuilt): void
    {
        CableCalculation::query()->where('project_id', $asbuilt->project_id)->whereNull('asbuilt_file_id')->get()
            ->each(fn (CableCalculation $calculation) => $this->sync($calculation));
    }

    /**
     * Removes the calculation's files from its project (when the calculation is deleted).
     */
    public function remove(CableCalculation $calculation): void
    {
        $calculation->projectFile?->delete();

        if ($calculation->asbuiltFile !== null) {
            $this->removeFromAsbuilt($calculation->asbuiltFile);
        }
    }

    protected function putInAsbuilt(?Asbuilt $asbuilt, ?AsbuiltFile $file, string $pdf, string $name, ?User $user): ?AsbuiltFile
    {
        // A closed as-built (its ZIP is made) and a battery room's (no cable dimensions) stay as they are.
        if ($asbuilt === null || $asbuilt->isClosed() || ! in_array(AsbuiltCategory::CableDimensions, $asbuilt->categories(), true)) {
            return $file;
        }

        $disk = Storage::disk(Asbuilt::DISK);
        $path = 'asbuilt/'.$asbuilt->id.'/'.Str::random(8).'-'.$name;
        $disk->put($path, $pdf);
        $attributes = ['path' => $path, 'original_name' => $name, 'size' => strlen($pdf)];

        if ($file === null) {
            return AsbuiltFile::query()->create([
                'asbuilt_id' => $asbuilt->id,
                'category' => AsbuiltCategory::CableDimensions->value,
                'uploaded_by' => $user?->id,
                ...$attributes,
            ]);
        }

        $previous = $file->path;
        $file->update($attributes);
        if (filled($previous) && $previous !== $path) {
            $disk->delete($previous);
        }

        return $file;
    }

    protected function removeFromAsbuilt(AsbuiltFile $file): void
    {
        if (! $file->asbuilt?->isClosed()) {
            $file->delete();
        }
    }
}
