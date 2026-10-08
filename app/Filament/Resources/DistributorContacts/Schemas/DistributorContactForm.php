<?php

namespace App\Filament\Resources\DistributorContacts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DistributorContactForm
{
    public static function configure(Schema $schema, bool $withDistributor = true): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('first_name')
                    ->label(__('erp.fields.first_name'))
                    ->required()
                    ->maxLength(60),
                TextInput::make('last_name')
                    ->label(__('erp.fields.last_name'))
                    ->required()
                    ->maxLength(60),
                TextInput::make('email')
                    ->label(__('erp.fields.email'))
                    ->email()
                    ->maxLength(150),
                TextInput::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->tel()
                    ->maxLength(30),
                TextInput::make('job_title')
                    ->label(__('erp.fields.job_title'))
                    ->maxLength(100),
                Select::make('distributor_id')
                    ->label(__('erp.resources.distributor.singular'))
                    ->relationship('distributor', 'name')
                    ->preload()
                    ->visible($withDistributor),
            ]);
    }
}
