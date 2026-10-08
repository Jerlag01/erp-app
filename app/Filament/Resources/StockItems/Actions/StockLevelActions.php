<?php

namespace App\Filament\Resources\StockItems\Actions;

use App\Actions\AdjustStock;
use App\Models\StockLevel;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The +, − and count actions on a stock row, used on the item page (per location)
 * and on the location page (per item). They need the "stock.adjust" permission.
 */
class StockLevelActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        $canAdjust = fn (): bool => modules()->stock() && (bool) auth()->user()?->hasPermission('stock.adjust');

        return [
            static::change('add', 1, Heroicon::OutlinedPlus, 'success')->visible($canAdjust),
            static::change('remove', -1, Heroicon::OutlinedMinus, 'danger')->visible($canAdjust),
            Action::make('count')
                ->label(__('erp.stock.count'))
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->visible($canAdjust)
                ->modalHeading(fn (StockLevel $record): string => __('erp.stock.count').': '.$record->item->name.' · '.$record->location->name)
                ->modalDescription(fn (StockLevel $record): string => __('erp.stock.count_help', ['current' => $record->item->unit->format($record->quantity)]))
                ->fillForm(fn (StockLevel $record): array => ['quantity' => (float) $record->quantity])
                ->schema(fn (StockLevel $record): array => [
                    static::quantityInput($record)->minValue(0)->label(__('erp.stock.counted')),
                    TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                ])
                ->action(fn (StockLevel $record, array $data) => app(AdjustStock::class)->setTo($record, (float) $data['quantity'], auth()->user(), $data['note'] ?: __('erp.stock.stocktake')))
                ->successNotificationTitle(__('erp.stock.saved')),
            Action::make('minimum')
                ->label(__('erp.stock.location_threshold'))
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('gray')
                ->visible(fn (): bool => modules()->stock() && (bool) auth()->user()?->hasPermission('stock_items.update'))
                ->fillForm(fn (StockLevel $record): array => ['min_quantity' => $record->min_quantity])
                ->schema(fn (StockLevel $record): array => [
                    TextInput::make('min_quantity')
                        ->label(__('erp.stock.location_threshold'))
                        ->helperText(__('erp.help.location_threshold', ['threshold' => $record->item->low_stock_threshold !== null ? $record->item->unit->format($record->item->low_stock_threshold) : '—']))
                        ->numeric()
                        ->minValue(0)
                        ->suffix($record->item->unit->abbreviation),
                ])
                ->action(fn (StockLevel $record, array $data) => $record->update(['min_quantity' => $data['min_quantity']]))
                ->successNotificationTitle(__('erp.stock.saved')),
        ];
    }

    protected static function change(string $name, int $direction, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__("erp.stock.{$name}"))
            ->icon($icon)
            ->color($color)
            ->button()
            ->size('sm')
            ->modalWidth('sm')
            ->modalHeading(fn (StockLevel $record): string => __("erp.stock.{$name}").': '.$record->item->name)
            ->modalDescription(fn (StockLevel $record): string => $record->location->name.' · '.__('erp.stock.in_stock', ['quantity' => $record->item->unit->format($record->quantity)]))
            ->fillForm(['quantity' => 1])
            ->schema(fn (StockLevel $record): array => [
                static::quantityInput($record)->minValue(fn (): float => $record->item->unit->allows_decimals ? 0.001 : 1)->autofocus(),
                TextInput::make('note')->label(__('erp.fields.note'))->placeholder(__('erp.stock.note_placeholder'))->maxLength(255),
            ])
            ->action(fn (StockLevel $record, array $data) => app(AdjustStock::class)->handle($record, $direction * (float) $data['quantity'], auth()->user(), $data['note'] ?? null))
            ->successNotificationTitle(__('erp.stock.saved'));
    }

    protected static function quantityInput(StockLevel $level): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('erp.fields.quantity'))
            ->numeric()
            ->step($level->item->unit->allows_decimals ? 0.001 : 1)
            ->suffix($level->item->unit->abbreviation)
            ->required();
    }
}
