<?php

namespace App\Filament\Client\Resources\AsbuiltStandardFiles\Pages;

use App\Enums\AsbuiltCategory;
use App\Filament\Client\Resources\AsbuiltStandardFiles\ClientAsbuiltStandardFileResource;
use App\Filament\Resources\Asbuilts\Pages\ViewAsbuilt;
use App\Models\AsbuiltLibraryFile;
use App\Models\WorkSite;
use App\Support\Asbuilts;
use App\Support\ClientPortal;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The as-built standard files of one COW-code in the client portal: an upload zone per category that
 * belongs to a COW-code. The client uploads new files and replaces existing ones; they remove only what
 * a client uploaded.
 */
class ViewClientAsbuiltStandardFiles extends ViewRecord
{
    protected static string $resource = ClientAsbuiltStandardFileResource::class;

    public function getTitle(): string
    {
        return __('erp.client_portal.asbuilt_library.singular', ['cow' => $this->getRecord()->cow_code]);
    }

    /**
     * The zones: "category value => label".
     *
     * @return array<string, string>
     */
    public static function zones(): array
    {
        return collect(Asbuilts::cowCodeCategories())->mapWithKeys(fn (AsbuiltCategory $category): array => [$category->value => $category->getLabel()])->all();
    }

    /**
     * The files of this COW-code per zone.
     *
     * @return Collection<string, Collection<int, AsbuiltLibraryFile>>
     */
    public function filesPerZone(): Collection
    {
        return $this->getRecord()->asbuiltLibraryFiles()
            ->whereIn('category', array_keys(self::zones()))
            ->with('uploader')
            ->orderBy('title')
            ->get()
            ->groupBy(fn (AsbuiltLibraryFile $file): string => $file->category->value);
    }

    protected function file(WorkSite $site, mixed $id): AsbuiltLibraryFile
    {
        return $site->asbuiltLibraryFiles()->whereIn('category', array_keys(self::zones()))->findOrFail((int) $id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('erp.client_portal.asbuilt_library.upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription(__('erp.client_portal.asbuilt_library.upload_help'))
                ->fillForm(fn (array $arguments): array => ['category' => $arguments['category'] ?? array_key_first(self::zones())])
                ->schema(fn (WorkSite $record): array => [
                    Select::make('category')->label(__('erp.asbuilt.category'))->options(self::zones())->required()->selectablePlaceholder(false),
                    ViewAsbuilt::upload('files', 'asbuilt-library/'.$record->id.'/client')->label(__('erp.asbuilt.files'))->required(),
                ])
                ->action(function (WorkSite $record, array $data): void {
                    $category = AsbuiltCategory::tryFrom((string) $data['category']);
                    abort_unless($category !== null && array_key_exists($category->value, self::zones()), 403);

                    $files = app(Asbuilts::class)->addCowCodeFiles($record, $category, (array) $data['files'], (array) ($data['files_names'] ?? []), client: ClientPortal::user());
                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', count($files), ['count' => count($files)]))->success()->send();
                }),
            // Called from the file rows with the file's id.
            Action::make('replace')
                ->label(__('erp.client_portal.asbuilt_library.replace'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading(fn (WorkSite $record, array $arguments): string => __('erp.client_portal.asbuilt_library.replace_heading', ['name' => $this->file($record, $arguments['file'] ?? 0)->original_name]))
                ->modalDescription(__('erp.client_portal.asbuilt_library.replace_help'))
                ->schema(fn (WorkSite $record): array => [
                    FileUpload::make('file')
                        ->hiddenLabel()
                        ->disk(AsbuiltLibraryFile::DISK)
                        ->directory('asbuilt-library/'.$record->id.'/client')
                        ->visibility('private')
                        ->preserveFilenames(false)
                        ->storeFileNamesIn('file_name')
                        ->maxSize(ViewAsbuilt::MAX_KILOBYTES)
                        ->required(),
                ])
                ->action(function (WorkSite $record, array $data, array $arguments): void {
                    $file = $this->file($record, $arguments['file'] ?? 0);
                    app(Asbuilts::class)->replaceCowCodeFile($file, (string) $data['file'], $data['file_name'] ?? null, client: ClientPortal::user());
                    Notification::make()->title(__('erp.client_portal.asbuilt_library.replaced'))->success()->send();
                }),
            Action::make('remove')
                ->label(__('erp.actions.delete'))
                ->color('danger')
                ->extraAttributes(['class' => 'hidden'])
                ->requiresConfirmation()
                ->modalDescription(__('erp.client_portal.asbuilt_library.remove_help'))
                ->action(function (WorkSite $record, array $arguments): void {
                    $file = $this->file($record, $arguments['file'] ?? 0);
                    // Only what a client uploaded; the files of the employees stay.
                    abort_unless($file->uploaded_by_client_user_id !== null, 403);
                    $file->delete();
                    Notification::make()->title(__('erp.asbuilt.file_removed'))->success()->send();
                }),
        ];
    }
}
