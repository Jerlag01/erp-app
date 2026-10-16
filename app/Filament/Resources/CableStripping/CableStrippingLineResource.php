<?php

namespace App\Filament\Resources\CableStripping;

use App\Enums\NavigationGroup;
use App\Filament\Resources\CableStripping\Pages\ManageCableStrippingLines;
use App\Models\CableStrippingLine;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use UnitEnum;

/**
 * "Kabel ontmantelen": the cable stripping list of the Excel. Everyone with access adds lines freely;
 * removing lines and the PDF overview need their own right.
 */
class CableStrippingLineResource extends Resource
{
    protected static ?string $model = CableStrippingLine::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'cable-stripping';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('erp.cable_stripping.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.cable_stripping.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.cable_stripping.title');
    }

    protected static function kg(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 2, ',', '.').' kg';
    }

    public static function form(Schema $schema): Schema
    {
        $number = fn (string $name): TextInput => TextInput::make($name)
            ->label(__("erp.cable_stripping.fields.{$name}"))
            ->numeric()
            ->minValue(0)
            ->maxValue(100000)
            ->step('any')
            ->suffix('kg')
            ->live(onBlur: true);

        return $schema
            ->columns(6)
            ->components([
                DatePicker::make('date')
                    ->label(__('erp.cable_stripping.fields.date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required()
                    ->columnSpan(2),
                TextInput::make('imputation')
                    ->label(__('erp.cable_stripping.fields.imputation'))
                    ->helperText(__('erp.cable_stripping.fields.imputation_help'))
                    ->datalist(fn (): array => WorkSite::query()->orderBy('cow_code')->limit(3000)->pluck('cow_code')->all())
                    ->maxLength(100)
                    ->columnSpan(2),
                TextInput::make('bin_number')
                    ->label(__('erp.cable_stripping.fields.bin_number'))
                    ->placeholder('PI06')
                    ->maxLength(30)
                    ->columnSpan(2),
                Select::make('workers')
                    ->label(__('erp.cable_stripping.fields.workers'))
                    ->relationship('workers', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                    ->multiple()
                    ->preload()
                    ->default(fn (): array => array_filter([auth()->id()]))
                    ->columnSpan(4),
                TimePicker::make('start_time')->label(__('erp.cable_stripping.fields.start_time'))->seconds(false)->live(onBlur: true)->columnSpan(1),
                TimePicker::make('end_time')->label(__('erp.cable_stripping.fields.end_time'))->seconds(false)->after('start_time')->live(onBlur: true)->columnSpan(1),
                Section::make(__('erp.cable_stripping.weighing'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        $number('weight_before'),
                        $number('empty_bin_weight'),
                        $number('weight_after'),
                        // The calculated columns, live while filling in.
                        Text::make(function (Get $get): string {
                            $line = new CableStrippingLine([
                                'weight_before' => $get('weight_before'), 'empty_bin_weight' => $get('empty_bin_weight'), 'weight_after' => $get('weight_after'),
                            ]);

                            return __('erp.cable_stripping.calculated', [
                                'before' => self::kg($line->cableBefore()),
                                'stripped' => self::kg($line->strippedCable()),
                                'waste' => self::kg($line->waste()),
                            ]);
                        })->columnSpanFull(),
                    ]),
                TextInput::make('notes')->label(__('erp.fields.notes'))->maxLength(500)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $kgColumn = fn (string $name, string $label, \Closure $state): TextColumn => TextColumn::make($name)
            ->label($label)
            ->state($state)
            ->numeric(2, ',', '.')
            ->alignEnd()
            ->fontFamily(FontFamily::Mono)
            ->placeholder('—');

        // Totals of the calculated columns over the filtered lines.
        $total = fn (string $expression): Summarizer => Summarizer::make()
            ->label(__('erp.cable_stripping.total'))
            ->numeric(2, ',', '.')
            ->using(fn (QueryBuilder $query): float => (float) $query->selectRaw("coalesce(sum({$expression}), 0) as total")->value('total'));

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('workers'))
            ->defaultSort('date', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->emptyStateHeading(__('erp.cable_stripping.empty'))
            ->emptyStateIcon(Heroicon::OutlinedScissors)
            ->columns([
                TextColumn::make('date')->label(__('erp.cable_stripping.fields.date'))->date('d/m/Y')->sortable(),
                TextColumn::make('imputation')->label(__('erp.cable_stripping.fields.imputation'))->fontFamily(FontFamily::Mono)->searchable()->sortable()->placeholder('—'),
                TextColumn::make('workers.name')->label(__('erp.cable_stripping.fields.workers'))->listWithLineBreaks()->searchable(),
                TextColumn::make('start_time')->label(__('erp.cable_stripping.fields.start_time'))->time('H:i')->placeholder('—'),
                TextColumn::make('end_time')->label(__('erp.cable_stripping.fields.end_time'))->time('H:i')->placeholder('—'),
                TextColumn::make('hours')
                    ->label(__('erp.cable_stripping.fields.hours'))
                    ->state(fn (CableStrippingLine $record): ?float => $record->hours())
                    ->numeric(2, ',', '.')
                    ->alignEnd()
                    ->placeholder('—')
                    ->summarize($total('(time_to_sec(end_time) - time_to_sec(start_time)) / 3600')),
                TextColumn::make('weight_before')->label(__('erp.cable_stripping.fields.weight_before'))->numeric(2, ',', '.')->alignEnd()->fontFamily(FontFamily::Mono)->placeholder('—')
                    ->summarize(Sum::make()->label(__('erp.cable_stripping.total'))->numeric(2, ',', '.')),
                TextColumn::make('empty_bin_weight')->label(__('erp.cable_stripping.fields.empty_bin_weight'))->numeric(2, ',', '.')->alignEnd()->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('weight_after')->label(__('erp.cable_stripping.fields.weight_after'))->numeric(2, ',', '.')->alignEnd()->fontFamily(FontFamily::Mono)->placeholder('—')
                    ->summarize(Sum::make()->label(__('erp.cable_stripping.total'))->numeric(2, ',', '.')),
                $kgColumn('cable_before', __('erp.cable_stripping.fields.cable_before'), fn (CableStrippingLine $record): ?float => $record->cableBefore())
                    ->summarize($total('weight_before - empty_bin_weight')),
                $kgColumn('waste', __('erp.cable_stripping.fields.waste'), fn (CableStrippingLine $record): ?float => $record->waste())
                    ->summarize($total('(weight_before - empty_bin_weight) - (weight_after - empty_bin_weight)')),
                TextColumn::make('bin_number')->label(__('erp.cable_stripping.fields.bin_number'))->fontFamily(FontFamily::Mono)->searchable()->sortable()->placeholder('—'),
                $kgColumn('stripped', __('erp.cable_stripping.fields.stripped'), fn (CableStrippingLine $record): ?float => $record->strippedCable())
                    ->weight('bold')
                    ->summarize($total('weight_after - empty_bin_weight')),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.cable_stripping.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.cable_stripping.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until) => $query->whereDate('date', '<=', $until)))
                    ->indicateUsing(fn (array $data): ?string => ($data['from'] ?? null) || ($data['until'] ?? null)
                        ? trim(($data['from'] ? date('d/m/Y', strtotime($data['from'])) : '…').' – '.($data['until'] ? date('d/m/Y', strtotime($data['until'])) : '…'))
                        : null),
                SelectFilter::make('workers')
                    ->label(__('erp.cable_stripping.fields.workers'))
                    ->relationship('workers', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCableStrippingLines::route('/'),
        ];
    }
}
