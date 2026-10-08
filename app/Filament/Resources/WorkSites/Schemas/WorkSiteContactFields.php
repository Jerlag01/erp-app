<?php

namespace App\Filament\Resources\WorkSites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The contact person fields, used on the contacts page and to add one straight from a select.
 */
class WorkSiteContactFields
{
    /**
     * @return list<TextInput>
     */
    public static function make(): array
    {
        return [
            TextInput::make('first_name')
                ->label(__('erp.fields.first_name'))
                ->required()
                ->maxLength(60),
            TextInput::make('last_name')
                ->label(__('erp.fields.last_name'))
                ->required()
                ->maxLength(60),
            TextInput::make('company')
                ->label(__('erp.settings.fields.company_name'))
                ->maxLength(120),
            TextInput::make('email')
                ->label(__('erp.fields.email'))
                ->email()
                ->prefixIcon(Heroicon::OutlinedEnvelope)
                ->maxLength(150),
            TextInput::make('phone')
                ->label(__('erp.fields.phone'))
                ->tel()
                ->prefixIcon(Heroicon::OutlinedPhone)
                ->maxLength(30),
        ];
    }
}
