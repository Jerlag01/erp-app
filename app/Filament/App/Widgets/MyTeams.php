<?php

namespace App\Filament\App\Widgets;

use App\Models\Team;
use App\Models\User;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The teams the signed-in employee belongs to, with their colleagues.
 */
class MyTeams extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return modules()->teams() && (bool) auth()->user()?->teams()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.my.teams'))
            ->query(fn (): Builder => Team::query()
                ->whereHas('members', fn (Builder $query) => $query->whereKey(auth()->id()))
                ->with(['leader', 'members'])
                ->withCount(modules()->fleet() ? ['vehicles'] : []))
            ->paginated(false)
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->width('1%'),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold'),
                TextColumn::make('leader.name')
                    ->label(__('erp.fields.leader'))
                    ->placeholder('—'),
                TextColumn::make('members')
                    ->label(__('erp.sections.members'))
                    ->state(fn (Team $record): string => $record->members->map(fn (User $user): string => $user->name)->implode(', '))
                    ->wrap(),
                TextColumn::make('vehicles_count')
                    ->label(__('erp.resources.vehicle.plural'))
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => modules()->fleet()),
            ]);
    }
}
