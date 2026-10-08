<?php

namespace App\Filament\Admin\Resources\Suggestions;

use App\Enums\NavigationGroup;
use App\Enums\SuggestionStatus;
use App\Filament\Admin\Resources\Suggestions\Pages\ListSuggestions;
use App\Filament\Admin\Resources\Suggestions\Pages\ViewSuggestion;
use App\Models\Suggestion;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The idea/complaint box: one tab per type, PDF export like the incidents. Administrators only.
 */
class SuggestionResource extends Resource
{
    protected static ?string $model = Suggestion::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'idea-box';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Safety;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.resources.suggestion.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.suggestion.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Suggestion::query()->where('status', SuggestionStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function pdfUrl(iterable $ids): string
    {
        return route('suggestions.pdf', ['suggestions' => collect($ids)->values()->all()]);
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
                                    ->label(__('erp.suggestions.type'))
                                    ->badge(),
                                TextEntry::make('submitted_at')
                                    ->label(__('erp.suggestions.submitted_at'))
                                    ->dateTime('d/m/Y H:i')
                                    ->fontFamily(FontFamily::Mono),
                                TextEntry::make('first_name')
                                    ->label(__('erp.fields.first_name'))
                                    ->placeholder('—'),
                                TextEntry::make('last_name')
                                    ->label(__('erp.fields.last_name'))
                                    ->placeholder('—'),
                                IconEntry::make('may_be_public')
                                    ->label(__('erp.suggestions.may_be_public'))
                                    ->boolean(),
                                TextEntry::make('user.email')
                                    ->label(__('erp.fields.email'))
                                    ->placeholder('—'),
                                TextEntry::make('description')
                                    ->label(__('erp.suggestions.description'))
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
                                    ->helperText(fn (Suggestion $record): ?string => $record->handled_at?->format('d/m/Y H:i'))
                                    ->placeholder('—'),
                                TextEntry::make('response')
                                    ->label(__('erp.suggestions.response'))
                                    ->extraAttributes(['style' => 'white-space: pre-wrap;'])
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make(__('erp.incidents.photos'))
                    ->columnSpanFull()
                    ->visible(fn (Suggestion $record): bool => filled($record->photos))
                    ->schema([
                        ViewEntry::make('photos')
                            ->hiddenLabel()
                            ->view('filament.admin.private-photos', ['routeName' => 'suggestions.photo', 'parameter' => 'suggestion']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateHeading(__('erp.suggestions.none'))
            ->emptyStateIcon(Heroicon::OutlinedLightBulb)
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('erp.suggestions.submitted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable(),
                TextColumn::make('submitter_name')
                    ->label(__('erp.incidents.reporter'))
                    ->searchable(['submitter_name', 'first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('erp.suggestions.description'))
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                IconColumn::make('may_be_public')
                    ->label(__('erp.suggestions.public_short'))
                    ->boolean()
                    ->falseIcon(Heroicon::OutlinedLockClosed)
                    ->falseColor('gray'),
                TextColumn::make('photos')
                    ->label(__('erp.incidents.photos'))
                    ->state(fn (Suggestion $record): int => count($record->photos ?? []))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(SuggestionStatus::class)
                    ->multiple(),
                TernaryFilter::make('may_be_public')
                    ->label(__('erp.suggestions.may_be_public')),
                SelectFilter::make('user')
                    ->label(__('erp.incidents.reporter'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.incidents.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.incidents.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->iconButton()
                    ->tooltip(__('erp.incidents.export_pdf'))
                    ->url(fn (Suggestion $record): string => static::pdfUrl([$record->id]), shouldOpenInNewTab: true),
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
            'index' => ListSuggestions::route('/'),
            'view' => ViewSuggestion::route('/{record}'),
        ];
    }
}
