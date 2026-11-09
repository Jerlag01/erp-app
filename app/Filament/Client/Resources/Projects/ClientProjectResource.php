<?php

namespace App\Filament\Client\Resources\Projects;

use App\Enums\GpwStatus;
use App\Enums\ProjectStatus;
use App\Filament\Client\ClientResource;
use App\Models\Project;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * All projects of the categories the client user may see (600, 604, 605, 608, 610–650): number,
 * COW code, status and PO numbers. No amounts. A closed battery room (609) shows its files, except the
 * invoices, electrical schematics and plans (ClientPortal::sharedFiles()).
 */
class ClientProjectResource extends ClientResource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    public static function permission(): string
    {
        return ClientPortal::PROJECTS;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->projects();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.projects');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.project');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.projects');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = Project::query()->with(['category', 'workSite', 'poNumbers', 'gpwRequests' => fn ($query) => $query->where('status', '!=', GpwStatus::Cancelled)]);

        return ClientPortal::user()?->restrictProjects($query) ?? $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $categories = ClientPortal::user()?->projectCategories() ?? [];

        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.no_projects'))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('erp.client_portal.project_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                // The number with the category's name ("604 · Batterijvervanging") and the project's description
                // under it, so the client sees what it belongs to.
                TextColumn::make('category.code')
                    ->label(__('erp.client_portal.category'))
                    ->formatStateUsing(fn (Project $record): string => $record->category?->label() ?? '')
                    ->badge()
                    ->color('gray')
                    ->description(fn (Project $record): ?string => $record->short_description ?: null),
                TextColumn::make('cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Project $record): ?string => $record->workSite?->city)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('short_description')
                    ->label(__('erp.client_portal.description'))
                    ->state(fn (Project $record): ?string => $record->subtitle())
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('erp.client_portal.status'))
                    ->badge()
                    ->sortable(),
                // The latest GPW request: its period, and whether it is approved or planned.
                TextColumn::make('gpw')
                    ->label(__('erp.gpw.short'))
                    ->state(fn (Project $record): ?string => ($gpw = $record->gpwRequests->first()) ? $gpw->status->getLabel().' · '.$gpw->period() : null)
                    ->badge()
                    ->color(fn (Project $record): string => $record->gpwRequests->first()?->status->getColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('po_numbers')
                    ->label(__('erp.client_portal.po_numbers'))
                    ->state(fn (Project $record): array => $record->poNumbers->pluck('number')->all())
                    ->fontFamily(FontFamily::Mono)
                    ->listWithLineBreaks()
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('poNumbers', fn (Builder $query) => $query->where('number', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('erp.client_portal.created'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('files')
                    ->label(__('erp.client_portal.files'))
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->visible(fn (Project $record): bool => ClientPortal::sharesFiles($record))
                    ->modalHeading(fn (Project $record): string => __('erp.client_portal.files_of', ['reference' => $record->reference]))
                    ->modalContent(fn (Project $record) => view('filament.client.project-files', ['project' => $record, 'files' => ClientPortal::sharedFiles($record)]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('erp.actions.close')),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('erp.client_portal.category'))
                    ->options(array_intersect_key(ClientPortal::categoryOptions(), array_flip($categories)))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $code) => $query->whereHas('category', fn (Builder $query) => $query->where('code', $code)))),
                SelectFilter::make('status')
                    ->label(__('erp.client_portal.status'))
                    ->options(ProjectStatus::class)
                    ->multiple(),
                TernaryFilter::make('has_po')
                    ->label(__('erp.client_portal.has_po'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('poNumbers'),
                        false: fn (Builder $query) => $query->whereDoesntHave('poNumbers'),
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientProjects::route('/')];
    }
}
