<?php

namespace App\Filament\App\Widgets;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use App\Support\DueDate;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vehicles the employee drives, or that belong to one of their teams.
 */
class MyVehicles extends TableWidget
{
    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return modules()->fleet() && static::query()->exists();
    }

    /**
     * @return Builder<Vehicle>
     */
    public static function query(): Builder
    {
        $user = auth()->user();

        return Vehicle::query()->where(function (Builder $query) use ($user): void {
            $query->where('driver_id', $user?->id);

            if (modules()->teams() && $user) {
                $query->orWhereIn('team_id', $user->teams()->select('teams.id'));
            }
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.my.vehicles'))
            ->query(fn (): Builder => static::query()->with(['team', 'driver']))
            ->defaultSort('plate_number')
            ->paginated(false)
            ->recordUrl(fn (Vehicle $record): ?string => auth()->user()->can('view', $record) ? VehicleResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('plate_number')
                    ->label(__('erp.fields.plate_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('brand')
                    ->label(__('erp.fields.vehicle'))
                    ->formatStateUsing(fn (Vehicle $record): string => "{$record->brand} {$record->type}")
                    ->description(fn (Vehicle $record): ?string => $record->driver?->name),
                TextColumn::make('next_control_date')
                    ->label(__('erp.fields.next_control_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Vehicle $record): string => DueDate::color($record->next_control_date))
                    ->placeholder('—'),
            ]);
    }
}
