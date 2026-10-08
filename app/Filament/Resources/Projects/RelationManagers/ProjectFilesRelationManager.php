<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Models\Project;
use App\Models\ProjectFile;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * One upload zone of a project: lists the uploaded files with their latest version, and lets
 * people with the right permission upload files, upload a new version, or remove a file.
 * Each zone is a small subclass that only names its section.
 */
abstract class ProjectFilesRelationManager extends RelationManager
{
    /**
     * Largest file in a project zone, in kilobytes (100 MB, the nginx limit).
     */
    public const MAX_KILOBYTES = 102400;

    abstract public static function section(): ProjectFileSection;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return static::section()->getLabel();
    }

    public static function getIcon(Model $ownerRecord, string $pageClass): Heroicon
    {
        return static::section()->getIcon();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        $count = $ownerRecord->filesIn(static::section())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows(static::section()->isFinancial() ? 'viewFinance' : 'view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canManage(): bool
    {
        return Gate::allows(static::section()->isFinancial() ? 'manageFinance' : 'manageFiles', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        $section = static::section();
        $canManage = fn (): bool => $this->canManage();
        $downloadUrl = fn (ProjectFile $record): string => route('projects.file', ['project' => $record->project_id, 'file' => $record]);

        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('latestVersion.user'))
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('erp.projects.files.empty'))
            ->emptyStateDescription($section->acceptedHint())
            ->emptyStateIcon($section->getIcon())
            ->recordUrl($downloadUrl)
            ->openRecordUrlInNewTab()
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->state(fn (ProjectFile $record): ?string => $record->latestVersion?->isPreviewableImage() ? $downloadUrl($record) : null)
                    ->imageHeight(56)
                    ->visible($section === ProjectFileSection::Images),
                TextColumn::make('name')
                    ->label(__('erp.projects.files.name'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (ProjectFile $record): ?string => $record->latestVersion ? strtoupper(pathinfo($record->name, PATHINFO_EXTENSION)).' · '.$record->latestVersion->humanSize() : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->label(__('erp.projects.files.version'))
                    ->formatStateUsing(fn (int $state): string => "v{$state}")
                    ->badge()
                    ->color(fn (int $state): string => $state > 1 ? 'info' : 'gray'),
                TextColumn::make('updated_at')
                    ->label(__('erp.projects.files.uploaded'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ProjectFile $record): ?string => $record->latestVersion?->user?->name)
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label(__('erp.projects.files.upload'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible($canManage)
                    ->authorize($canManage)
                    ->modalHeading(__('erp.projects.files.upload_to', ['section' => $section->getLabel()]))
                    ->schema([
                        $this->upload('files')
                            ->multiple()
                            ->maxFiles(50)
                            ->storeFileNamesIn('names')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        foreach ((array) $data['files'] as $path) {
                            ProjectFile::store($this->getOwnerRecord(), static::section(), $path, $data['names'][$path] ?? null, auth()->user());
                        }

                        Notification::make()->title(__('erp.projects.files.uploaded_count', ['count' => count((array) $data['files'])]))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('erp.projects.files.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url($downloadUrl, shouldOpenInNewTab: true),
                Action::make('newVersion')
                    ->label(__('erp.projects.files.new_version'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->visible($canManage)
                    ->authorize($canManage)
                    ->modalHeading(fn (ProjectFile $record): string => __('erp.projects.files.new_version_of', ['name' => $record->name]))
                    ->modalDescription(__('erp.projects.files.new_version_help'))
                    ->schema([
                        $this->upload('file')
                            ->storeFileNamesIn('name')
                            ->required(),
                    ])
                    ->action(function (ProjectFile $record, array $data): void {
                        $path = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $names = (array) ($data['name'] ?? []);

                        ProjectFile::store($this->getOwnerRecord(), static::section(), $path, $names[$path] ?? (is_string($data['name'] ?? null) ? $data['name'] : null), auth()->user(), $record);

                        Notification::make()->title(__('erp.projects.files.version_saved'))->success()->send();
                    }),
                Action::make('versions')
                    ->label(__('erp.projects.files.versions'))
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (ProjectFile $record): bool => $record->version > 1)
                    ->modalHeading(fn (ProjectFile $record): string => __('erp.projects.files.versions_of', ['name' => $record->name]))
                    ->modalContent(fn (ProjectFile $record) => view('filament.projects.file-versions', ['file' => $record, 'versions' => $record->versions()->with('user')->get()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('erp.projects.files.close')),
                DeleteAction::make()
                    ->visible($canManage)
                    ->authorize($canManage)
                    ->modalDescription(__('erp.projects.files.delete_help')),
            ]);
    }

    /**
     * An upload field for this zone, checked on the file extension (DWG and the like have no
     * reliable MIME type) and stored on the private disk.
     */
    protected function upload(string $name): FileUpload
    {
        $section = static::section();

        return FileUpload::make($name)
            ->hiddenLabel()
            ->helperText($section->acceptedHint().' '.__('erp.projects.files.max_size', ['size' => self::MAX_KILOBYTES / 1024]))
            ->disk(ProjectFile::DISK)
            ->directory(fn (): string => ProjectFile::directoryFor($this->getOwnerRecord(), $section))
            ->visibility('private')
            ->maxSize(self::MAX_KILOBYTES)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($section): void {
                $name = $value instanceof TemporaryUploadedFile ? $value->getClientOriginalName() : (string) $value;

                if (! $section->accepts($name)) {
                    $fail(__('erp.projects.files.type_not_allowed', ['name' => Str::limit($name, 60)]));
                }
            });
    }
}
