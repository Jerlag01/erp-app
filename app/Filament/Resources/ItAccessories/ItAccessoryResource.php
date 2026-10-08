<?php

namespace App\Filament\Resources\ItAccessories;

use App\Enums\ItItemKind;
use App\Filament\Resources\It\ItItemResource;
use App\Filament\Resources\ItAccessories\Pages\CreateItAccessory;
use App\Filament\Resources\ItAccessories\Pages\EditItAccessory;
use App\Filament\Resources\ItAccessories\Pages\ListItAccessorys;
use App\Filament\Resources\ItAccessories\Pages\ViewItAccessory;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ItAccessoryResource extends ItItemResource
{
    protected static ?string $slug = 'it-accessories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?int $navigationSort = 3;

    public static function kind(): ItItemKind
    {
        return ItItemKind::Accessory;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItAccessorys::route('/'),
            'create' => CreateItAccessory::route('/create'),
            'view' => ViewItAccessory::route('/{record}'),
            'edit' => EditItAccessory::route('/{record}/edit'),
        ];
    }
}
