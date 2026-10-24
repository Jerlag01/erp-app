<?php

namespace App\Filament\Resources\WasteEntryLines;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WasteEntries\WasteEntryResource;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * The battery list of each region: a row per line of a battery entry (destruction certificate),
 * with its own weight and its own PDF (uploaded here or with the entry), also when several lines
 * share the same entry and reference. Exported per
 * year with the PDF of each line behind it.
 */
class WasteEntryLineResource extends Resource
{
    protected static ?string $model = WasteEntryLine::class;

    protected static ?string $slug = 'waste-battery-lists';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBattery50;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return __('erp.waste.regional_lists');
    }

    public static function getModelLabel(): string
    {
        return __('erp.waste.line');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.waste.regional_lists');
    }

    public static function getEloquentQuery(): Builder
    {
        return WasteEntryLine::query()
            ->join('waste_entries', 'waste_entries.id', '=', 'waste_entry_lines.waste_entry_id')
            ->select('waste_entry_lines.*')
            ->with(['entry.category', 'entry.processor']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('waste_entries.date', 'desc')
            ->emptyStateHeading(__('erp.waste.no_entries'))
            ->columns([
                TextColumn::make('entry.date')->label(__('erp.fields.date'))->date('d/m/Y')->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('waste_entries.date', $direction)),
                TextColumn::make('entry.category.name')->label(__('erp.waste.type'))->description(fn (WasteEntryLine $record): ?string => $record->entry?->category?->waste_code)->toggleable(),
                TextColumn::make('entry.processor_reference')->label(__('erp.waste.processor_reference'))->fontFamily(FontFamily::Mono)->searchable(query: fn (Builder $query, string $search) => $query->where('waste_entries.processor_reference', 'like', '%'.addcslashes($search, '%_\\').'%'))->placeholder('—'),
                TextColumn::make('project_reference')->label(__('erp.waste.project_reference'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('cow_code')->label(__('erp.fields.cow_code'))->fontFamily(FontFamily::Mono)->weight('bold')->searchable()->placeholder('—'),
                TextColumn::make('destruction_certificate')->label(__('erp.waste.destruction_certificate_short'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('po_number')->label(__('erp.waste.po_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('weight_kg')
                    ->label(__('erp.waste.weight'))
                    ->formatStateUsing(fn ($state): string => WasteEntry::formatKg($state))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('erp.waste.total'))->formatStateUsing(fn ($state): string => WasteEntry::formatKg($state))),
                IconColumn::make('pdf')
                    ->label(__('erp.waste.pdf_short'))
                    ->state(fn (WasteEntryLine $record): bool => filled($record->pdf_path))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedDocumentText)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->url(fn (WasteEntryLine $record): ?string => filled($record->pdf_path) ? route('waste.line-pdf', $record) : null, shouldOpenInNewTab: true),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.waste.year'))
                    ->options(fn (): array => WasteEntryResource::yearOptions())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereYear('waste_entries.date', (int) $year))),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.incidents.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.incidents.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('waste_entries.date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('waste_entries.date', '<=', $date))),
            ])
            ->recordActions([
                // The PDF of this line, also added or replaced later.
                Action::make('uploadPdf')
                    ->label(fn (WasteEntryLine $record): string => filled($record->pdf_path) ? __('erp.waste.replace_pdf') : __('erp.waste.upload_pdf'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color(fn (WasteEntryLine $record): string => filled($record->pdf_path) ? 'gray' : 'primary')
                    ->visible(fn (WasteEntryLine $record): bool => $record->entry !== null && Gate::allows('update', $record->entry))
                    ->modalHeading(fn (WasteEntryLine $record): string => __('erp.waste.upload_pdf_heading', ['certificate' => $record->destruction_certificate ?? '—', 'date' => $record->entry?->date?->format('d/m/Y')]))
                    ->schema([
                        FileUpload::make('pdf')
                            ->label(__('erp.waste.pdf'))
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk(WasteEntryLine::DISK)
                            ->directory(fn (): string => 'waste/'.now()->format('Y'))
                            ->visibility('private')
                            ->storeFileNamesIn('pdf_name')
                            ->maxSize(51200)
                            ->required(),
                    ])
                    ->action(fn (WasteEntryLine $record, array $data) => $record->update(['pdf_path' => $data['pdf'], 'pdf_name' => $data['pdf_name'] ?? basename((string) $data['pdf'])]))
                    ->successNotificationTitle(__('erp.waste.pdf_saved')),
                Action::make('removePdf')
                    ->label(__('erp.waste.remove_pdf'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->iconButton()
                    ->visible(fn (WasteEntryLine $record): bool => filled($record->pdf_path) && $record->entry !== null && Gate::allows('update', $record->entry))
                    ->requiresConfirmation()
                    ->action(fn (WasteEntryLine $record) => $record->update(['pdf_path' => null, 'pdf_name' => null]))
                    ->successNotificationTitle(__('erp.waste.pdf_removed')),
            ])
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWasteEntryLines::route('/'),
        ];
    }
}
