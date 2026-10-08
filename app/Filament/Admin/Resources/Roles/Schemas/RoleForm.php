<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Models\Role;
use App\Support\Permissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        // Administrators cannot take admin rights away from their own role.
        $isOwnRole = fn (?Role $record): bool => $record !== null && auth()->user()?->role_id === $record->id;

        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('description')
                            ->label(__('erp.fields.description'))
                            ->maxLength(255),
                        Toggle::make('is_admin')
                            ->label(__('erp.roles.is_admin'))
                            ->helperText(fn (?Role $record): string => $record?->is_guest ? __('erp.roles.is_guest_help') : __('erp.roles.is_admin_help'))
                            ->live()
                            ->disabled(fn (?Role $record): bool => $isOwnRole($record) || (bool) $record?->is_guest)
                            ->dehydrated(fn (?Role $record): bool => ! $isOwnRole($record) && ! $record?->is_guest)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('erp.roles.permissions'))
                    ->description(__('erp.roles.permissions_help'))
                    ->hidden(fn (Get $get, ?Role $record): bool => (bool) $get('is_admin') || (bool) $record?->is_guest)
                    ->schema([
                        Grid::make(['md' => 2, 'xl' => 4])->schema(
                            collect(Permissions::all())
                                ->map(fn (array $abilities, string $area) => CheckboxList::make("permissions.{$area}")
                                    ->label(__("erp.permissions.areas.{$area}"))
                                    ->helperText(fn (): ?string => modules()->disabled(Permissions::moduleOf($area)) ? __('erp.roles.module_off') : null)
                                    ->options(collect($abilities)->mapWithKeys(fn (string $ability): array => [
                                        $ability => __("erp.permissions.abilities.{$ability}"),
                                    ])->all())
                                    ->bulkToggleable())
                                ->values()
                                ->all()
                        ),
                    ]),
            ]);
    }
}
