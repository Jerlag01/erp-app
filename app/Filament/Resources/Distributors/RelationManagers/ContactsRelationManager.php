<?php

namespace App\Filament\Resources\Distributors\RelationManagers;

use App\Filament\Resources\DistributorContacts\DistributorContactResource;
use App\Filament\Resources\DistributorContacts\Schemas\DistributorContactForm;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.distributor_contact.plural');
    }

    public function form(Schema $schema): Schema
    {
        return DistributorContactForm::configure($schema, withDistributor: false);
    }

    public function table(Table $table): Table
    {
        return DistributorContactResource::contactsTable($table, withDistributor: false)
            ->recordTitleAttribute('last_name')
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
