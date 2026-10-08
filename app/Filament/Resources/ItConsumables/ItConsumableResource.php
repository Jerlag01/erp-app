<?php

namespace App\Filament\Resources\ItConsumables;

use App\Enums\ItItemKind;
use App\Filament\Resources\It\ItItemResource;
use App\Filament\Resources\ItConsumables\Pages\CreateItConsumable;
use App\Filament\Resources\ItConsumables\Pages\EditItConsumable;
use App\Filament\Resources\ItConsumables\Pages\ListItConsumables;
use App\Filament\Resources\ItConsumables\Pages\ViewItConsumable;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ItConsumableResource extends ItItemResource
{
    protected static ?string $slug = 'it-consumables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 4;

    public static function kind(): ItItemKind
    {
        return ItItemKind::Consumable;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItConsumables::route('/'),
            'create' => CreateItConsumable::route('/create'),
            'view' => ViewItConsumable::route('/{record}'),
            'edit' => EditItConsumable::route('/{record}/edit'),
        ];
    }
}
