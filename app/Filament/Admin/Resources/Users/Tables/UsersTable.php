<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['role', 'teams']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (User $record): ?string => $record->job_title)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('erp.fields.email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role.name')
                    ->label(__('erp.resources.role.singular'))
                    ->state(fn (User $record): ?string => $record->effectiveRole()?->name)
                    ->description(fn (User $record): ?string => $record->isGuest() && ! $record->role?->is_guest ? __('erp.roles.no_team', ['role' => $record->role?->name]) : null)
                    ->badge()
                    ->color(fn (User $record): string => match (true) {
                        $record->isAdmin() => 'primary',
                        $record->isGuest() => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('initials')
                    ->label(__('erp.fields.initials'))
                    ->state(fn (User $record): string => $record->referenceInitials())
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vacation_days')
                    ->label(__('erp.fields.vacation_days'))
                    ->numeric(decimalPlaces: 1)
                    ->visible(fn (): bool => modules()->vacations())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('teams.name')
                    ->label(__('erp.resources.team.plural'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->teams()),
                IconColumn::make('is_active')
                    ->label(__('erp.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('erp.resources.role.singular'))
                    ->relationship('role', 'name')
                    ->preload(),
                SelectFilter::make('teams')
                    ->label(__('erp.resources.team.singular'))
                    ->relationship('teams', 'name')
                    ->preload()
                    ->visible(fn (): bool => modules()->teams()),
                TernaryFilter::make('is_active')
                    ->label(__('erp.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
