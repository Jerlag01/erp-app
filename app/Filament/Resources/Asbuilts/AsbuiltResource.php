<?php

namespace App\Filament\Resources\Asbuilts;

use App\Enums\AsbuiltStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Asbuilt;
use App\Models\ProjectCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * The as-builts: made when a project is executed, filled per category, inspected by Normec BTV and
 * closed into a ZIP for the client. The overview needs "asbuilt.view"; the employees on a project
 * open its as-built from the project.
 */
class AsbuiltResource extends Resource
{
    protected static ?string $model = Asbuilt::class;

    protected static ?string $slug = 'asbuilts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 30;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return __('erp.asbuilt.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.asbuilt.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.asbuilt.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::canViewAny() ? Asbuilt::query()->where('status', '!=', AsbuiltStatus::Closed)->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 5])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('project.reference')->label(__('erp.asbuilt.project'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)
                        ->url(fn (Asbuilt $record): ?string => $record->project && Gate::allows('view', $record->project) ? ProjectResource::getUrl('view', ['record' => $record->project]) : null),
                    TextEntry::make('status')->label(__('erp.fields.status'))->badge(),
                    TextEntry::make('created_at')->label(__('erp.asbuilt.created'))->date('d/m/Y'),
                    TextEntry::make('btv_date')->label(__('erp.asbuilt.btv_date'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('closed_at')->label(__('erp.asbuilt.closed'))->date('d/m/Y')->placeholder('—'),
                ]),
            ViewEntry::make('files')
                ->hiddenLabel()
                ->columnSpanFull()
                ->view('filament.asbuilts.files'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.category', 'project.workSite'])->withCount('files'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.asbuilt.none'))
            ->emptyStateDescription(__('erp.asbuilt.none_help'))
            ->columns([
                TextColumn::make('project.reference')->label(__('erp.asbuilt.project'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()
                    ->description(fn (Asbuilt $record): ?string => $record->project?->subtitle()),
                TextColumn::make('project.cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('status')->label(__('erp.fields.status'))->badge()->sortable(),
                TextColumn::make('created_at')->label(__('erp.asbuilt.created'))->date('d/m/Y')->sortable(),
                TextColumn::make('btv_date')->label(__('erp.asbuilt.btv_date'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('closed_at')->label(__('erp.asbuilt.closed'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('files_count')->label(__('erp.asbuilt.files'))->badge()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('erp.fields.status'))->options(AsbuiltStatus::class),
                SelectFilter::make('category')
                    ->label(__('erp.resources.project_category.singular'))
                    ->options(fn (): array => ProjectCategory::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query->whereHas('project', fn (Builder $query) => $query->where('project_category_id', $id)))),
            ])
            ->recordActions([
                Action::make('zip')
                    ->label(__('erp.asbuilt.download_zip'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Asbuilt $record): bool => $record->isClosed())
                    ->url(fn (Asbuilt $record): string => route('asbuilt.zip', $record)),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAsbuilts::route('/'),
            'view' => Pages\ViewAsbuilt::route('/{record}'),
        ];
    }
}
