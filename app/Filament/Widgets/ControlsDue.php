<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use App\Support\DueDate;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ControlsDue extends TableWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return modules()->fleet() && (bool) auth()->user()?->hasPermission('vehicles.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.due.controls_due'))
            ->query(fn (): Builder => Vehicle::query()->visibleTo(auth()->user())->controlDue()->with('team'))
            ->defaultSort('next_control_date')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Vehicle $record): string => VehicleResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('erp.due.nothing_due'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('plate_number')
                    ->label(__('erp.fields.plate_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('brand')
                    ->label(__('erp.fields.vehicle'))
                    ->formatStateUsing(fn (Vehicle $record): string => "{$record->brand} {$record->type}"),
                TextColumn::make('team.name')
                    ->label(__('erp.resources.team.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->teams()),
                TextColumn::make('next_control_date')
                    ->label(__('erp.fields.next_control_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Vehicle $record): string => DueDate::color($record->next_control_date))
                    ->description(fn (Vehicle $record): ?string => DueDate::description($record->next_control_date)),
            ]);
    }
}
