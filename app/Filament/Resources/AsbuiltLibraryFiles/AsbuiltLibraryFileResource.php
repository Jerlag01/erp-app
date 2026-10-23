<?php

namespace App\Filament\Resources\AsbuiltLibraryFiles;

use App\Enums\AsbuiltCategory;
use App\Enums\NavigationGroup;
use App\Models\AsbuiltLibraryFile;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use UnitEnum;

/**
 * The register of standard as-built files: per category, evacuation routes and external influences
 * per COW-code (added to every as-built of that COW-code), technical documents as a register to pick
 * from, and the other categories general or per COW-code. Kept with "asbuilt.library".
 */
class AsbuiltLibraryFileResource extends Resource
{
    protected static ?string $model = AsbuiltLibraryFile::class;

    protected static ?string $slug = 'asbuilt-standard-files';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 31;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return __('erp.asbuilt.library.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.asbuilt.library.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.asbuilt.library.title');
    }

    /**
     * @return array<int, string>
     */
    public static function siteOptions(): array
    {
        return WorkSite::query()->orderBy('cow_code')->get(['id', 'cow_code', 'city'])
            ->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' · '.$site->city, ' ·')])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('erp.asbuilt.library.file_title'))->required()->maxLength(255),
            Select::make('work_site_id')
                ->label(__('erp.client_portal.cow_code'))
                ->options(fn (): array => self::siteOptions())
                ->searchable()
                ->visible(fn (?AsbuiltLibraryFile $record): bool => ! $record?->category->isRegister() && ! $record?->category->isGlobal())
                ->required(fn (?AsbuiltLibraryFile $record): bool => (bool) $record?->category->perCowCode()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workSite', 'uploader'])->withCount('usages'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.asbuilt.library.none'))
            ->emptyStateDescription(__('erp.asbuilt.library.help'))
            ->columns([
                TextColumn::make('title')->label(__('erp.asbuilt.library.file_title'))->searchable()->weight('bold')
                    ->description(fn (AsbuiltLibraryFile $record): string => $record->original_name.' · '.Number::fileSize($record->size, precision: 1)),
                TextColumn::make('category')->label(__('erp.asbuilt.category'))->badge()->color('gray'),
                TextColumn::make('workSite.cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->searchable()
                    ->placeholder(fn (AsbuiltLibraryFile $record): string => $record->category->isRegister() ? __('erp.asbuilt.library.register') : __('erp.asbuilt.library.general')),
                TextColumn::make('usages_count')->label(__('erp.asbuilt.library.used_in'))->badge()->color('gray'),
                TextColumn::make('created_at')->label(__('erp.asbuilt.created'))->date('d/m/Y')->sortable()->description(fn (AsbuiltLibraryFile $record): ?string => $record->uploader?->name),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('erp.asbuilt.category'))->options(AsbuiltCategory::class),
                SelectFilter::make('work_site_id')->label(__('erp.client_portal.cow_code'))->options(fn (): array => self::siteOptions())->searchable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('erp.asbuilt.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (AsbuiltLibraryFile $record): string => route('asbuilt.library-file', $record)),
                EditAction::make(),
                DeleteAction::make()->modalDescription(fn (AsbuiltLibraryFile $record): string => __('erp.asbuilt.library.delete_help', ['count' => $record->usages_count ?? $record->usages()->count()])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAsbuiltLibraryFiles::route('/'),
        ];
    }
}
