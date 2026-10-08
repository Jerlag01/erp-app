<?php

namespace App\Filament\Resources\It;

use App\Models\ItAsset;
use App\Models\Location;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Check out to" fields: a user, a location or an asset. Non-administrators only see
 * their team members, their team locations and the assets they may see.
 */
class ItTargetFields
{
    /**
     * @param  list<string>  $types  any of 'user', 'location', 'asset'
     * @return list<ToggleButtons|Select>
     */
    public static function make(array $types = ['user', 'location', 'asset'], ?ItAsset $except = null): array
    {
        if (! modules()->locations()) {
            $types = array_values(array_diff($types, ['location']));
        }

        $is = fn (string $type): \Closure => fn (Get $get): bool => $get('target_type') === $type;

        return [
            ToggleButtons::make('target_type')
                ->label(__('erp.it.checkout_to'))
                ->options(collect($types)->mapWithKeys(fn (string $type): array => [$type => __("erp.it.targets.{$type}")])->all())
                ->icons(['user' => 'heroicon-o-user', 'location' => 'heroicon-o-map-pin', 'asset' => 'heroicon-o-computer-desktop'])
                ->default($types[0])
                ->inline()
                ->required()
                ->live()
                ->hidden(count($types) === 1),
            Select::make('user_id')
                ->label(__('erp.it.targets.user'))
                ->options(fn (): array => static::users()->pluck('name', 'id')->all())
                ->searchable()
                ->required($is('user'))
                ->visible($is('user')),
            Select::make('location_id')
                ->label(__('erp.it.targets.location'))
                ->options(fn (): array => Location::query()->where('is_active', true)->visibleTo(auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required($is('location'))
                ->visible($is('location')),
            Select::make('asset_id')
                ->label(__('erp.it.targets.asset'))
                ->options(fn (): array => ItAsset::query()->visibleTo(auth()->user())
                    ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
                    ->with('model.manufacturer')->orderBy('asset_tag')->get()
                    ->mapWithKeys(fn (ItAsset $asset): array => [$asset->id => $asset->label()])->all())
                ->searchable()
                ->required($is('asset'))
                ->visible($is('asset')),
        ];
    }

    /**
     * @return Builder<User>
     */
    public static function users(): Builder
    {
        $user = auth()->user();

        return User::query()->active()->orderBy('name')
            ->when($user?->seesOnlyOwnTeams(), fn (Builder $query) => $query->whereKey($user->teamMemberIds()));
    }
}
