<?php

namespace App\Filament\Resources\Asbuilts\Pages;

use App\Enums\AsbuiltCategory;
use App\Enums\AsbuiltStatus;
use App\Filament\Resources\Asbuilts\AsbuiltResource;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Support\Asbuilts;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * One as-built: its files per category (with an upload per category), the technical documents
 * picked from the register, the BTV inspection date and, once planned, the BTV report that closes it.
 */
class ViewAsbuilt extends ViewRecord
{
    protected static string $resource = AsbuiltResource::class;

    /** Largest file: 250 MB (the limit of Livewire's temporary uploads is 256 MB). */
    public const MAX_KILOBYTES = 256000;

    /**
     * The employees on the project open it from the project, also without "asbuilt.view".
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(modules()->asbuilt(), 403);
    }

    public function getTitle(): string
    {
        return __('erp.asbuilt.singular').' '.$this->getRecord()->project?->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->project?->subtitle();
    }

    /**
     * An upload zone for any file format, on the private disk, keeping the original names.
     */
    public static function upload(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->multiple()
            ->disk(Asbuilt::DISK)
            ->directory($directory)
            ->visibility('private')
            ->preserveFilenames(false)
            ->storeFileNamesIn($name.'_names')
            ->maxSize(self::MAX_KILOBYTES)
            ->maxFiles(100)
            ->panelLayout('grid');
    }

    public function removeFile(int $id): void
    {
        $asbuilt = $this->getRecord();
        abort_unless(Gate::allows('update', $asbuilt), 403);

        $asbuilt->files()->whereKey($id)->first()?->delete();

        Notification::make()->title(__('erp.asbuilt.file_removed'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $service = app(Asbuilts::class);

        return [
            Action::make('upload')
                ->label(__('erp.asbuilt.add_files'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (Asbuilt $record): bool => Gate::allows('update', $record))
                ->fillForm(fn (array $arguments): array => ['category' => $arguments['category'] ?? null])
                ->modalHeading(__('erp.asbuilt.add_files'))
                ->modalDescription(__('erp.asbuilt.add_files_help'))
                ->schema(fn (Asbuilt $record): array => [
                    Select::make('category')
                        ->label(__('erp.asbuilt.category'))
                        ->options(collect(AsbuiltCategory::ordered())->reject(fn (AsbuiltCategory $category): bool => $category === AsbuiltCategory::BtvReports)->mapWithKeys(fn (AsbuiltCategory $category): array => [$category->value => $category->getLabel()])->all())
                        ->required(),
                    self::upload('files', 'asbuilt/'.$record->id)->label(__('erp.asbuilt.files'))->required(),
                ])
                ->action(function (Asbuilt $record, array $data) use ($service): void {
                    $files = $service->addFiles($record, $data['category'], (array) $data['files'], (array) ($data['files_names'] ?? []), auth()->user());
                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', count($files), ['count' => count($files)]))->success()->send();
                }),
            Action::make('technical')
                ->label(__('erp.asbuilt.technical_documents'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->visible(fn (Asbuilt $record): bool => Gate::allows('update', $record))
                ->modalHeading(__('erp.asbuilt.technical_documents'))
                ->modalDescription(__('erp.asbuilt.technical_help'))
                ->schema(fn (Asbuilt $record): array => [
                    Select::make('library')
                        ->label(__('erp.asbuilt.pick_documents'))
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => AsbuiltLibraryFile::query()
                            ->where('category', AsbuiltCategory::TechnicalDocuments->value)
                            ->whereNotIn('id', $record->files()->whereNotNull('asbuilt_library_file_id')->pluck('asbuilt_library_file_id'))
                            ->orderBy('title')
                            ->get()
                            ->mapWithKeys(fn (AsbuiltLibraryFile $file): array => [$file->id => $file->title.' ('.$file->original_name.')'])
                            ->all()),
                    self::upload('files', 'asbuilt-library/technical_documents')->label(__('erp.asbuilt.new_documents')),
                ])
                ->action(function (Asbuilt $record, array $data) use ($service): void {
                    $added = $service->attach($record, array_map('intval', (array) ($data['library'] ?? [])));
                    $added += count($service->addFiles($record, AsbuiltCategory::TechnicalDocuments->value, (array) ($data['files'] ?? []), (array) ($data['files_names'] ?? []), auth()->user()));
                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', $added, ['count' => $added]))->success()->send();
                }),
            Action::make('planBtv')
                ->label(fn (Asbuilt $record): string => $record->btv_date ? __('erp.asbuilt.change_btv') : __('erp.asbuilt.plan_btv'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn (Asbuilt $record): bool => Gate::allows('update', $record))
                ->fillForm(fn (Asbuilt $record): array => ['btv_date' => $record->btv_date?->toDateString()])
                ->modalDescription(__('erp.asbuilt.plan_btv_help'))
                ->schema([
                    DatePicker::make('btv_date')->label(__('erp.asbuilt.btv_date'))->native(false)->displayFormat('d/m/Y'),
                ])
                ->action(fn (Asbuilt $record, array $data) => $service->planBtv($record, $data['btv_date'] ?? null))
                ->successNotificationTitle(__('erp.asbuilt.btv_saved')),
            Action::make('btvReport')
                ->label(__('erp.asbuilt.upload_btv'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->visible(fn (Asbuilt $record): bool => $record->status === AsbuiltStatus::BtvPlanned && Gate::allows('update', $record))
                ->modalDescription(fn (Asbuilt $record): string => __('erp.asbuilt.upload_btv_help', ['name' => $record->zipName()]))
                ->schema(fn (Asbuilt $record): array => [
                    self::upload('files', 'asbuilt/'.$record->id)->label(__('erp.enums.asbuilt_category.btv_reports'))->required(),
                ])
                ->action(function (Asbuilt $record, array $data) use ($service): void {
                    $service->uploadBtvReport($record, (array) $data['files'], (array) ($data['files_names'] ?? []), auth()->user());
                    $this->refreshFormData([]);
                    Notification::make()->title(__('erp.asbuilt.closed_done'))->body(__('erp.asbuilt.closed_done_body', ['name' => $record->zipName()]))->success()->send();
                }),
            Action::make('zip')
                ->label(__('erp.asbuilt.download_zip'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (Asbuilt $record): bool => $record->isClosed())
                ->url(fn (Asbuilt $record): string => route('asbuilt.zip', $record)),
            Action::make('reopen')
                ->label(__('erp.asbuilt.reopen'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible(fn (Asbuilt $record): bool => Gate::allows('reopen', $record))
                ->requiresConfirmation()
                ->modalDescription(__('erp.asbuilt.reopen_help'))
                ->action(fn (Asbuilt $record) => $service->reopen($record))
                ->successNotificationTitle(__('erp.asbuilt.reopened')),
            // Only on the admin side, with the explicit permission "asbuilt.delete".
            DeleteAction::make()
                ->modalDescription(__('erp.asbuilt.delete_help'))
                ->visible(fn (Asbuilt $record): bool => AsbuiltResource::deletableHere($record)),
        ];
    }

    /**
     * Files per category for the view, the client's general zone last.
     *
     * @return array<string, list<AsbuiltFile>>
     */
    public function filesByCategory(): array
    {
        $files = $this->getRecord()->files()->with(['libraryFile.uploader', 'uploader', 'clientUploader'])->get()->groupBy('category');
        $grouped = [];

        foreach (AsbuiltCategory::ordered() as $category) {
            $grouped[$category->value] = $files->get($category->value, collect())->all();
        }

        $grouped[AsbuiltCategory::GENERAL] = $files->get(AsbuiltCategory::GENERAL, collect())->all();

        return $grouped;
    }
}
