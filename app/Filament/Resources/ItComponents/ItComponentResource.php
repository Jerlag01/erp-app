<?php

namespace App\Filament\Resources\ItComponents;

use App\Enums\ItItemKind;
use App\Filament\Resources\It\ItItemResource;
use App\Filament\Resources\ItComponents\Pages\CreateItComponent;
use App\Filament\Resources\ItComponents\Pages\EditItComponent;
use App\Filament\Resources\ItComponents\Pages\ListItComponents;
use App\Filament\Resources\ItComponents\Pages\ViewItComponent;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ItComponentResource extends ItItemResource
{
    protected static ?string $slug = 'it-components';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 5;

    public static function kind(): ItItemKind
    {
        return ItItemKind::Component;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItComponents::route('/'),
            'create' => CreateItComponent::route('/create'),
            'view' => ViewItComponent::route('/{record}'),
            'edit' => EditItComponent::route('/{record}/edit'),
        ];
    }
}
