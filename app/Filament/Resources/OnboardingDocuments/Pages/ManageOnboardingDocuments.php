<?php

namespace App\Filament\Resources\OnboardingDocuments\Pages;

use App\Filament\Resources\OnboardingDocuments\OnboardingDocumentResource;
use App\Models\OnboardingDocument;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManageOnboardingDocuments extends ManageRecords
{
    protected static string $resource = OnboardingDocumentResource::class;

    /**
     * Several files at once, in one language: each becomes a document titled after its file name.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->schema([
                    FileUpload::make('files')
                        ->label(__('erp.onboarding.files'))
                        ->helperText(__('erp.onboarding.files_help'))
                        ->multiple()
                        ->disk(OnboardingDocument::DISK)
                        ->directory('onboarding/documents')
                        ->visibility('private')
                        ->maxSize(OnboardingDocumentResource::MAX_KILOBYTES)
                        ->storeFileNamesIn('names')
                        ->required(),
                    OnboardingDocumentResource::localeSelect(),
                    TextInput::make('sort')->label(__('erp.onboarding.sort'))->integer()->minValue(0)->default(fn (): int => (int) OnboardingDocument::query()->max('sort') + 1),
                ])
                ->using(function (array $data): Model {
                    $names = (array) ($data['names'] ?? []);
                    $sort = (int) ($data['sort'] ?? 0);
                    $first = null;

                    foreach (array_values((array) $data['files']) as $index => $path) {
                        $name = (string) ($names[$path] ?? basename($path));
                        $document = OnboardingDocument::query()->create([
                            'title' => Str::of(pathinfo($name, PATHINFO_FILENAME))->replace(['_', '-'], ' ')->squish()->toString() ?: $name,
                            'path' => $path,
                            'original_name' => $name,
                            'locale' => $data['locale'],
                            'sort' => $sort + $index,
                            'is_active' => true,
                        ]);
                        $first ??= $document;
                    }

                    return $first;
                }),
        ];
    }
}
