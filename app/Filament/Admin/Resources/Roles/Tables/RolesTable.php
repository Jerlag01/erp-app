<?php

namespace App\Filament\Admin\Resources\Roles\Tables;

use App\Models\Role;
use App\Support\Permissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        $total = collect(Permissions::all())->flatten()->count();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (Role $record): ?string => $record->is_guest ? __('erp.roles.is_guest_help') : $record->description)
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label(__('erp.roles.is_admin'))
                    ->boolean(),
                TextColumn::make('permissions')
                    ->label(__('erp.roles.permissions'))
                    ->state(fn (Role $record): string => $record->permissionCount()." / {$total}")
                    ->badge()
                    ->color('gray'),
                TextColumn::make('users_count')
                    ->label(__('erp.resources.user.plural'))
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting the own role and roles that still have users.
                DeleteAction::make(),
            ]);
    }
}
