<?php

namespace App\Filament\Resources\AsbuiltLibraryFiles\Pages;

use App\Enums\AsbuiltCategory;
use App\Filament\Resources\AsbuiltLibraryFiles\AsbuiltLibraryFileResource;
use App\Filament\Resources\Asbuilts\Pages\ViewAsbuilt;
use App\Models\Asbuilt;
use App\Models\AsbuiltLibraryFile;
use App\Support\Asbuilts;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageAsbuiltLibraryFiles extends ManageRecords
{
    protected static string $resource = AsbuiltLibraryFileResource::class;

    protected function getHeaderActions(): array
    {
        $category = fn (Get $get): ?AsbuiltCategory => AsbuiltCategory::tryFrom((string) $get('category'));

        return [
            // An upload zone: one standard file per uploaded file.
            Action::make('upload')
                ->label(__('erp.asbuilt.library.upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => AsbuiltLibraryFileResource::canCreate())
                ->modalDescription(__('erp.asbuilt.library.upload_help'))
                ->schema([
                    Select::make('category')
                        ->label(__('erp.asbuilt.category'))
                        ->options(collect(AsbuiltCategory::ordered())->mapWithKeys(fn (AsbuiltCategory $item): array => [$item->value => $item->getLabel()])->all())
                        ->required()
                        ->live(),
                    Select::make('work_site_id')
                        ->label(__('erp.client_portal.cow_code'))
                        ->helperText(fn (Get $get): string => $category($get)?->perCowCode() ? __('erp.asbuilt.library.cow_required') : __('erp.asbuilt.library.cow_optional'))
                        ->options(fn (): array => AsbuiltLibraryFileResource::siteOptions())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $category($get) !== null && ! $category($get)->isRegister() && ! $category($get)->isGlobal())
                        ->required(fn (Get $get): bool => (bool) $category($get)?->perCowCode()),
                    ViewAsbuilt::upload('files', 'asbuilt-library')->label(__('erp.asbuilt.files'))->required(),
                ])
                ->action(function (array $data): void {
                    $type = AsbuiltCategory::from($data['category']);
                    $names = (array) ($data['files_names'] ?? []);
                    $count = 0;

                    foreach ((array) $data['files'] as $path) {
                        $name = (string) ($names[$path] ?? basename($path));
                        $file = AsbuiltLibraryFile::query()->create([
                            'category' => $type,
                            'work_site_id' => $type->isRegister() || $type->isGlobal() ? null : ($data['work_site_id'] ?? null),
                            'title' => pathinfo($name, PATHINFO_FILENAME),
                            'path' => $path,
                            'original_name' => $name,
                            'size' => Storage::disk(Asbuilt::DISK)->exists($path) ? Storage::disk(Asbuilt::DISK)->size($path) : 0,
                            'uploaded_by' => auth()->id(),
                        ]);
                        app(Asbuilts::class)->spreadToOpen($file);
                        $count++;
                    }

                    Notification::make()->title(trans_choice('erp.asbuilt.files_added', $count, ['count' => $count]))->body(__('erp.asbuilt.library.upload_done'))->success()->send();
                }),
        ];
    }
}
