<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Filament\Schemas\AddressFields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('erp.sections.location'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->placeholder(__('erp.help.location_name'))
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),
                        TextInput::make('code')
                            ->label(__('erp.fields.code'))
                            ->helperText(__('erp.help.location_code'))
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null),
                        ...AddressFields::make(),
                        TextInput::make('phone')
                            ->label(__('erp.fields.phone'))
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label(__('erp.fields.email'))
                            ->email()
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.is_active'))
                            ->default(true)
                            ->inline(false),
                        Textarea::make('notes')
                            ->label(__('erp.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
