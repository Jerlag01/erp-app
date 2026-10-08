<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\TextInput;

/**
 * Street, postal code, city and country fields, used by locations, manufacturers and distributors.
 */
class AddressFields
{
    /**
     * @return list<TextInput>
     */
    public static function make(): array
    {
        return [
            TextInput::make('street')
                ->label(__('erp.fields.street'))
                ->maxLength(150)
                ->columnSpanFull(),
            TextInput::make('postal_code')
                ->label(__('erp.fields.postal_code'))
                ->maxLength(10),
            TextInput::make('city')
                ->label(__('erp.fields.city'))
                ->maxLength(100),
            TextInput::make('country')
                ->label(__('erp.fields.country'))
                ->default(__('erp.fields.default_country'))
                ->maxLength(100),
        ];
    }
}
