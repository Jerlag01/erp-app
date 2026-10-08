<?php

namespace App\Filament\Admin\Resources\Incidents;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Admin\Resources\Incidents\Pages\ViewIncident;
use App\Models\Incident;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Incident submissions, per employee, with PDF export. Administrators only.
 */
class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Safety;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.resources.incident.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.incident.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Incident::query()->where('status', IncidentStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function pdfUrl(iterable $ids): string
    {
        return route('incidents.pdf', ['incidents' => collect($ids)->values()->all()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 2])
                            ->columns(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('erp.incidents.type'))
                                    ->badge(),
                                TextEntry::make('reported_at')
                                    ->label(__('erp.incidents.reported_at'))
                                    ->dateTime('d/m/Y H:i')
                                    ->fontFamily(FontFamily::Mono),
                                TextEntry::make('reporter_name')
                                    ->label(__('erp.incidents.reporter'))
                                    ->helperText(fn (Incident $record): ?string => $record->user?->email),
                                TextEntry::make('place')
                                    ->label(__('erp.incidents.place'))
                                    ->state(fn (Incident $record): ?string => $record->placeName())
                                    ->helperText(fn (Incident $record): ?string => $record->location_details)
                                    ->placeholder(fn (Incident $record): string => $record->location_details ?? '—'),
                                IconEntry::make('other_victims')
                                    ->label(__('erp.incidents.other_victims'))
                                    ->boolean()
                                    ->trueColor('danger')
                                    ->falseColor('gray'),
                                IconEntry::make('material_damage')
                                    ->label(__('erp.incidents.material_damage'))
                                    ->boolean()
                                    ->trueColor('warning')
                                    ->falseColor('gray'),
                                TextEntry::make('other_victims_details')
                                    ->label(__('erp.incidents.other_victims_details'))
                                    ->visible(fn (Incident $record): bool => filled($record->other_victims_details)),
                                TextEntry::make('material_damage_details')
                                    ->label(__('erp.incidents.material_damage_details'))
                                    ->visible(fn (Incident $record): bool => filled($record->material_damage_details)),
                                TextEntry::make('description')
                                    ->label(__('erp.incidents.description'))
                                    ->extraAttributes(['style' => 'white-space: pre-wrap;'])
                                    ->columnSpanFull(),
                            ]),
                        Section::make(__('erp.incidents.handling'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('erp.fields.status'))
                                    ->badge(),
                                TextEntry::make('handler.name')
                                    ->label(__('erp.incidents.handled_by'))
                                    ->helperText(fn (Incident $record): ?string => $record->handled_at?->format('d/m/Y H:i'))
                                    ->placeholder('—'),
                                TextEntry::make('follow_up')
                                    ->label(__('erp.incidents.follow_up'))
                                    ->extraAttributes(['style' => 'white-space: pre-wrap;'])
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make(__('erp.incidents.photos'))
                    ->columnSpanFull()
                    ->visible(fn (Incident $record): bool => filled($record->photos))
                    ->schema([
                        ViewEntry::make('photos')
                            ->hiddenLabel()
                            ->view('filament.admin.private-photos', ['routeName' => 'incidents.photo', 'parameter' => 'incident']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['place', 'user']))
            ->defaultSort('reported_at', 'desc')
            ->defaultGroup(Group::make('reporter_name')->label(__('erp.incidents.reporter'))->collapsible())
            ->groups([
                Group::make('reporter_name')->label(__('erp.incidents.reporter'))->collapsible(),
                Group::make('type')->label(__('erp.incidents.type'))->getTitleFromRecordUsing(fn (Incident $record): string => $record->type->getLabel()),
            ])
            ->emptyStateHeading(__('erp.incidents.none'))
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->columns([
                TextColumn::make('reported_at')
                    ->label(__('erp.incidents.reported_at'))
                    ->dateTime('d/m/Y H:i')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable(),
                TextColumn::make('reporter_name')
                    ->label(__('erp.incidents.reporter'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('erp.incidents.type'))
                    ->badge(),
                TextColumn::make('place_label')
                    ->label(__('erp.incidents.place'))
                    ->state(fn (Incident $record): ?string => $record->placeName() ?? $record->location_details)
                    ->limit(50)
                    ->placeholder('—')
                    ->searchable(['place_label', 'location_details']),
                IconColumn::make('other_victims')
                    ->label(__('erp.incidents.other_victims_short'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray'),
                IconColumn::make('material_damage')
                    ->label(__('erp.incidents.material_damage_short'))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray'),
                TextColumn::make('photos')
                    ->label(__('erp.incidents.photos'))
                    ->state(fn (Incident $record): int => count($record->photos ?? []))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label(__('erp.incidents.reporter'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label(__('erp.incidents.type'))
                    ->options(IncidentType::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(IncidentStatus::class)
                    ->multiple(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.incidents.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.incidents.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('reported_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('reported_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->iconButton()
                    ->tooltip(__('erp.incidents.export_pdf'))
                    ->url(fn (Incident $record): string => static::pdfUrl([$record->id]), shouldOpenInNewTab: true),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportPdf')
                        ->label(__('erp.incidents.export_pdf'))
                        ->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(static::pdfUrl($records->modelKeys())).', "_blank")'))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncidents::route('/'),
            'view' => ViewIncident::route('/{record}'),
        ];
    }

    /**
     * Employees who reported something, for the per-employee export.
     *
     * @return array<int, string>
     */
    public static function reporters(): array
    {
        return User::query()->whereIn('id', Incident::query()->select('user_id'))->orderBy('name')->pluck('name', 'id')->all();
    }
}
