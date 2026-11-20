<?php

namespace App\Filament\Resources\BtvControls\Pages;

use App\Enums\ProjectFileSection;
use App\Filament\Resources\BtvControls\BtvControlResource;
use App\Models\BtvControl;
use App\Models\ProjectFile;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ManageBtvControls extends ManageRecords
{
    protected static string $resource = BtvControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->using(function (array $data): BtvControl {
                    $control = BtvControl::query()->create([...collect($data)->except(['report_upload', 'report_upload_name'])->all(), 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                    self::storeReport($control, $data);

                    return $control;
                }),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('erp.btv.tabs.open'))->modifyQueryUsing(fn (Builder $query) => $query->whereNull('project_file_id')),
            'reported' => Tab::make(__('erp.btv.tabs.reported'))->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('project_file_id')),
            'all' => Tab::make(__('erp.btv.tabs.all')),
        ];
    }

    /**
     * Moves an uploaded report into the project's files ("BTV control"; a new version when replaced).
     *
     * @param  array<string, mixed>  $data
     */
    public static function storeReport(BtvControl $control, array $data): void
    {
        $path = $data['report_upload'] ?? null;
        $path = is_array($path) ? (array_values($path)[0] ?? null) : $path;

        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            return;
        }

        $name = $data['report_upload_name'] ?? null;
        $name = is_array($name) ? (array_values($name)[0] ?? null) : $name;
        $control->loadMissing('project', 'report');

        // Into the project's own folder, as the other project files.
        $target = ProjectFile::directoryFor($control->project, ProjectFileSection::BtvControl).'/'.basename($path);
        Storage::disk('local')->move($path, $target);

        $control->attachReport($target, $name ?: basename($path), auth()->user());
    }
}
