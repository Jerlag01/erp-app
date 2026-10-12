<?php

namespace App\Filament\Resources\WorkSites\Actions;

use App\Enums\BuildingType;
use App\Models\WorkSite;
use App\Support\WorkSiteImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * "Import from Excel": upload an .xlsx or .csv file, choose per column which field it fills, then
 * import. Sites are matched on their COW code.
 */
class ImportWorkSitesAction
{
    public static function make(): Action
    {
        $path = fn (Get $get): ?string => self::uploadedFile($get('file'))?->getRealPath() ?: null;
        $headers = fn (Get $get): array => ($file = $path($get)) ? rescue(fn (): array => app(WorkSiteImport::class)->headers($file), [], false) : [];

        return Action::make('import')
            ->label(__('erp.work_site_import.title'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('create', WorkSite::class))
            ->authorize(fn (): bool => Gate::allows('create', WorkSite::class))
            ->modalWidth(Width::FourExtraLarge)
            ->steps([
                Step::make(__('erp.work_site_import.steps.file'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('erp.work_site_import.file'))
                            ->helperText(__('erp.work_site_import.file_help'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                            ])
                            ->maxSize(20480)
                            ->storeFiles(false)
                            ->required(),
                    ]),
                Step::make(__('erp.work_site_import.steps.columns'))
                    ->description(__('erp.work_site_import.columns_help'))
                    ->schema(fn (Get $get): array => [
                        Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                            fn (string $header, int $index): Select => Select::make("mapping.{$index}")
                                ->label($header)
                                ->options(WorkSiteImport::targets())
                                ->placeholder(__('erp.work_site_import.ignore'))
                                ->default(WorkSiteImport::guess($header)),
                            $headers($get),
                            array_keys($headers($get)),
                        )),
                        Text::make(fn (): HtmlString => new HtmlString(e(__('erp.work_site_import.cow_required')))),
                    ]),
                Step::make(__('erp.work_site_import.steps.options'))
                    ->schema([
                        Select::make('default_type')
                            ->label(__('erp.work_site_import.default_type'))
                            ->helperText(__('erp.work_site_import.default_type_help'))
                            ->options(BuildingType::class)
                            ->default(BuildingType::Unknown->value)
                            ->required(),
                        Toggle::make('update_existing')
                            ->label(__('erp.work_site_import.update_existing'))
                            ->helperText(__('erp.work_site_import.update_existing_help')),
                    ]),
            ])
            ->action(function (array $data): void {
                $realPath = self::uploadedFile($data['file'] ?? null)?->getRealPath() ?: null;

                if (! $realPath) {
                    Notification::make()->title(__('erp.work_site_import.no_file'))->danger()->send();

                    return;
                }

                $mapping = collect((array) ($data['mapping'] ?? []))->map(fn (mixed $field): ?string => filled($field) ? (string) $field : null)->all();
                $result = app(WorkSiteImport::class)->import(
                    $realPath,
                    $mapping,
                    ($data['default_type'] ?? null) instanceof BuildingType ? $data['default_type'] : (BuildingType::tryFrom((string) ($data['default_type'] ?? '')) ?? BuildingType::Unknown),
                    (bool) ($data['update_existing'] ?? false),
                );

                $body = __('erp.work_site_import.result', ['created' => $result['created'], 'updated' => $result['updated'], 'unchanged' => $result['unchanged'], 'skipped' => count($result['skipped'])]);

                if ($result['skipped'] !== []) {
                    $body .= '<br>'.collect($result['skipped'])->take(10)->map(fn (string $line): string => e($line))->implode('<br>');
                }

                Notification::make()
                    ->title(__('erp.work_site_import.done'))
                    ->body($body)
                    ->{$result['skipped'] === [] ? 'success' : 'warning'}()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * The upload is one file, or an array of them while the form is being filled in.
     */
    protected static function uploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        return is_array($state) ? collect($state)->first(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile) : null;
    }
}
