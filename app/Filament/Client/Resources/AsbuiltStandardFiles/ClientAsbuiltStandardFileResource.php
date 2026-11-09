<?php

namespace App\Filament\Client\Resources\AsbuiltStandardFiles;

use App\Filament\Client\ClientResource;
use App\Models\WorkSite;
use App\Support\Asbuilts;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "As-built standard files" in the client portal: per COW-code the user sees (also without a project in
 * their list), an upload zone per category that belongs to a COW-code (evacuation routes, battery room
 * plans, ventilation calculation, evacuation list); not the global or general files. What the client
 * uploads goes straight into the register and into the open as-builts of that COW-code.
 */
class ClientAsbuiltStandardFileResource extends ClientResource
{
    protected static ?string $model = WorkSite::class;

    protected static ?string $slug = 'asbuilt-standard-files';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderArrowDown;

    protected static ?int $navigationSort = 4;

    public static function permission(): string
    {
        return ClientPortal::ASBUILT_LIBRARY;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->asbuilt() && modules()->workSites();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.asbuilt_library.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.cow_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.asbuilt_library.title');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = ClientPortal::user();
        $query = WorkSite::query()
            ->with('area')
            ->withCount(['asbuiltLibraryFiles' => fn (Builder $query) => $query->whereIn('category', self::categoryValues())]);

        return $user ? $user->restrictWorkSites($query) : $query->whereRaw('1 = 0');
    }

    /**
     * @return list<string>
     */
    public static function categoryValues(): array
    {
        return array_map(fn ($category): string => $category->value, Asbuilts::cowCodeCategories());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 4])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold),
                    TextEntry::make('address')->label(__('erp.client_portal.address'))->state(fn (WorkSite $record): ?string => $record->addressLine())->placeholder('—')->columnSpan(2),
                    TextEntry::make('area.name')->label(__('erp.resources.work_site_area.singular'))->placeholder('—'),
                ]),
            ViewEntry::make('standard_files')
                ->hiddenLabel()
                ->columnSpanFull()
                ->view('filament.client.asbuilt-standard-files'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('cow_code')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->description(__('erp.client_portal.asbuilt_library.help'))
            ->columns([
                TextColumn::make('cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()->sortable(),
                TextColumn::make('city')->label(__('erp.client_portal.address'))->state(fn (WorkSite $record): ?string => $record->addressLine())->searchable(['street', 'city', 'postal_code'])->placeholder('—'),
                TextColumn::make('area.name')->label(__('erp.resources.work_site_area.singular'))->placeholder('—'),
                TextColumn::make('asbuilt_library_files_count')->label(__('erp.client_portal.asbuilt_library.files'))->badge()->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('has_files')
                    ->label(__('erp.client_portal.asbuilt_library.has_files'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('asbuiltLibraryFiles', fn (Builder $query) => $query->whereIn('category', self::categoryValues())),
                        false: fn (Builder $query) => $query->whereDoesntHave('asbuiltLibraryFiles', fn (Builder $query) => $query->whereIn('category', self::categoryValues())),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label(__('erp.client_portal.asbuilt_library.open'))->icon(Heroicon::OutlinedArrowUpTray),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientAsbuiltStandardFiles::route('/'),
            'view' => Pages\ViewClientAsbuiltStandardFiles::route('/{record}'),
        ];
    }
}
