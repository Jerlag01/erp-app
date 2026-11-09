<?php

namespace App\Filament\Client\Resources\Asbuilts\Pages;

use App\Enums\AsbuiltCategory;
use App\Filament\Client\Resources\Asbuilts\ClientAsbuiltResource;
use App\Filament\Resources\Asbuilts\Pages\ViewAsbuilt;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Support\Asbuilts;
use App\Support\ClientPortal;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One as-built in the client portal: its status, every file in it per category (to download, also
 * while the as-built is still open), the ZIP once finished, and the upload zone for the files the
 * client provides (per category, or at once in the general zone, e.g. one ZIP).
 */
class ViewClientAsbuilt extends ViewRecord
{
    protected static string $resource = ClientAsbuiltResource::class;

    public function getTitle(): string
    {
        return __('erp.asbuilt.singular').' '.$this->getRecord()->project?->reference;
    }

    /**
     * @return array<string, string>
     */
    public static function uploadCategories(?Asbuilt $asbuilt = null): array
    {
        return [
            AsbuiltCategory::GENERAL => __('erp.asbuilt.general_zone_client'),
            ...collect($asbuilt?->categories() ?? AsbuiltCategory::ordered())->filter(fn (AsbuiltCategory $category): bool => $category->clientMayUpload())
                ->mapWithKeys(fn (AsbuiltCategory $category): array => [$category->value => $category->getLabel()])->all(),
        ];
    }

    /**
     * Every category the client sees, as on the employees' side: each category of this as-built in
     * order (also while empty), the general zone last.
     *
     * @return array<string, string>
     */
    public static function visibleCategories(Asbuilt $asbuilt): array
    {
        return [
            ...collect($asbuilt->categories())->mapWithKeys(fn (AsbuiltCategory $category): array => [$category->value => $category->getLabel()])->all(),
            AsbuiltCategory::GENERAL => __('erp.asbuilt.general_zone_client'),
        ];
    }

    /**
     * Every file of the as-built, per category: the client sees and downloads them all, not only what
     * they handed in, and not only once the as-built is finished.
     *
     * @return array<string, list<AsbuiltFile>>
     */
    public function clientFiles(): array
    {
        return $this->getRecord()->files()
            ->with('libraryFile', 'clientUploader')
            ->orderBy('id')
            ->get()
            ->filter(fn (AsbuiltFile $file): bool => $file->storagePath() !== null)
            ->groupBy('category')->map->values()->map->all()->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            // At any moment: the final ZIP once closed, else one of what is in it now (same folders).
            Action::make('zip')
                ->label(__('erp.asbuilt.download_zip'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (Asbuilt $record): string => route('client.asbuilt.zip', $record)),
            Action::make('upload')
                ->label(__('erp.asbuilt.client_upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (Asbuilt $record): bool => ! $record->isClosed())
                ->modalDescription(__('erp.asbuilt.client_upload_help'))
                ->fillForm(fn (array $arguments): array => ['category' => $arguments['category'] ?? AsbuiltCategory::GENERAL])
                ->schema(fn (Asbuilt $record): array => [
                    Select::make('category')->label(__('erp.asbuilt.category'))->options(self::uploadCategories($record))->required()->selectablePlaceholder(false),
                    ViewAsbuilt::upload('files', 'asbuilt/'.$record->id.'/client')->label(__('erp.asbuilt.files'))->required(),
                ])
                ->action(function (Asbuilt $record, array $data): void {
                    abort_unless(array_key_exists($data['category'], self::uploadCategories($record)) && ! $record->isClosed(), 403);

                    $files = app(Asbuilts::class)->addFiles($record, $data['category'], (array) $data['files'], (array) ($data['files_names'] ?? []), client: ClientPortal::user());
                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', count($files), ['count' => count($files)]))->body(__('erp.asbuilt.client_upload_done'))->success()->send();
                }),
        ];
    }
}
