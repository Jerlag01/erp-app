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
 * One as-built in the client portal: its status, the ZIP once finished, and the upload zone for
 * the files the client provides (per category, or at once in the general zone, e.g. one ZIP).
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
    public static function uploadCategories(): array
    {
        return [
            AsbuiltCategory::GENERAL => __('erp.asbuilt.general_zone_client'),
            ...collect(AsbuiltCategory::ordered())->filter(fn (AsbuiltCategory $category): bool => $category->clientMayUpload())
                ->mapWithKeys(fn (AsbuiltCategory $category): array => [$category->value => $category->getLabel()])->all(),
        ];
    }

    /**
     * What this client handed in, per category.
     *
     * @return array<string, list<AsbuiltFile>>
     */
    public function clientFiles(): array
    {
        return $this->getRecord()->files()
            ->where(fn ($query) => $query
                ->whereNotNull('uploaded_by_client_user_id')
                ->orWhereHas('libraryFile', fn ($query) => $query->whereNotNull('uploaded_by_client_user_id')))
            ->with('libraryFile', 'clientUploader')->get()->groupBy('category')->map->all()->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('zip')
                ->label(__('erp.asbuilt.download_zip'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (Asbuilt $record): bool => $record->isClosed())
                ->url(fn (Asbuilt $record): string => route('client.asbuilt.zip', $record)),
            Action::make('upload')
                ->label(__('erp.asbuilt.client_upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (Asbuilt $record): bool => ! $record->isClosed())
                ->modalDescription(__('erp.asbuilt.client_upload_help'))
                ->fillForm(fn (array $arguments): array => ['category' => $arguments['category'] ?? AsbuiltCategory::GENERAL])
                ->schema(fn (Asbuilt $record): array => [
                    Select::make('category')->label(__('erp.asbuilt.category'))->options(self::uploadCategories())->required()->selectablePlaceholder(false),
                    ViewAsbuilt::upload('files', 'asbuilt/'.$record->id.'/client')->label(__('erp.asbuilt.files'))->required(),
                ])
                ->action(function (Asbuilt $record, array $data): void {
                    abort_unless(array_key_exists($data['category'], self::uploadCategories()) && ! $record->isClosed(), 403);

                    $files = app(Asbuilts::class)->addFiles($record, $data['category'], (array) $data['files'], (array) ($data['files_names'] ?? []), client: ClientPortal::user());
                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', count($files), ['count' => count($files)]))->body(__('erp.asbuilt.client_upload_done'))->success()->send();
                }),
        ];
    }
}
