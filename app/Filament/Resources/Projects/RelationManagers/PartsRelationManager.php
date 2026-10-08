<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\ProjectPart;
use App\Models\StockItem;
use App\Models\Unit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The part list of a project: stock items (found while typing) or free lines, each with an
 * amount and unit such as 150 m of cable. Nothing is taken out of stock (yet).
 */
class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.projects.parts.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        $count = $ownerRecord->parts()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('stock_item_id')
                    ->label(__('erp.projects.parts.item'))
                    ->helperText(__('erp.projects.parts.item_help'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchItems($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($item = StockItem::find($value)) ? self::itemLabel($item) : null)
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($item = StockItem::find($state)) {
                            $set('description', $item->name);
                            $set('unit_id', $item->unit_id);
                        }
                    })
                    ->visible(fn (): bool => modules()->stock())
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->label(__('erp.fields.description'))
                    ->helperText(fn (): ?string => modules()->stock() ? __('erp.projects.parts.description_help') : null)
                    ->required(fn (Get $get): bool => blank($get('stock_item_id')))
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->label(__('erp.projects.parts.quantity'))
                    ->numeric()
                    ->minValue(0.001)
                    ->maxValue(9999999)
                    ->step('any')
                    ->default(1)
                    ->required()
                    ->columnSpan(2),
                Select::make('unit_id')
                    ->label(__('erp.projects.parts.unit'))
                    ->options(fn (): array => Unit::query()->orderBy('name')->get()->mapWithKeys(fn (Unit $unit): array => [$unit->id => "{$unit->name} ({$unit->abbreviation})"])->all())
                    ->columnSpan(2),
                TextInput::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => Gate::allows('manageParts', $this->getOwnerRecord());

        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'stockItem.manufacturer']))
            ->emptyStateHeading(__('erp.projects.parts.empty'))
            ->emptyStateIcon(Heroicon::OutlinedListBullet)
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('description')
                    ->label(__('erp.projects.parts.item'))
                    ->description(fn (ProjectPart $record): ?string => $record->stockItem ? self::references($record->stockItem) : __('erp.projects.parts.not_in_stock'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('erp.projects.parts.quantity'))
                    ->formatStateUsing(fn (ProjectPart $record): string => $record->quantityLabel())
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd(),
                TextColumn::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('erp.projects.parts.add'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canManage)
                    ->authorize($canManage)
                    ->createAnother(true),
            ])
            ->recordActions([
                EditAction::make()->visible($canManage)->authorize($canManage),
                DeleteAction::make()->visible($canManage)->authorize($canManage),
            ]);
    }

    /**
     * Active stock items matching the name, a reference or the EAN code.
     *
     * @return array<int, string>
     */
    public static function searchItems(string $search): array
    {
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        return StockItem::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('manufacturer_reference', 'like', $term)
                ->orWhere('distributor_reference', 'like', $term)
                ->orWhere('ean', 'like', $term))
            ->with('manufacturer')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (StockItem $item): array => [$item->id => self::itemLabel($item)])
            ->all();
    }

    protected static function itemLabel(StockItem $item): string
    {
        return trim($item->name.' — '.self::references($item), ' —');
    }

    protected static function references(StockItem $item): string
    {
        return implode(' · ', array_filter([$item->manufacturer?->name, $item->manufacturer_reference, $item->ean]));
    }
}
