<?php

namespace App\Filament\Resources\WorkSiteContacts;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WorkSiteContacts\Pages\ManageWorkSiteContacts;
use App\Filament\Resources\WorkSites\Schemas\WorkSiteContactFields;
use App\Models\WorkSiteContact;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WorkSiteContactResource extends Resource
{
    protected static ?string $model = WorkSiteContact::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'work-site-contacts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::WorkSites;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.resources.work_site_contact.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.work_site_contact.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof WorkSiteContact ? $record->label() : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(WorkSiteContactFields::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['workSites', 'areas']))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('erp.fields.name'))
                    ->formatStateUsing(fn (WorkSiteContact $record): string => $record->fullName())
                    ->description(fn (WorkSiteContact $record): ?string => $record->company)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name', 'company'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('erp.fields.email'))
                    ->url(fn (WorkSiteContact $record): ?string => $record->email ? "mailto:{$record->email}" : null)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->url(fn (WorkSiteContact $record): ?string => $record->phone ? 'tel:'.preg_replace('/[^+\d]/', '', $record->phone) : null)
                    ->placeholder('—'),
                TextColumn::make('areas_count')
                    ->label(__('erp.resources.work_site_area.plural'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('work_sites_count')
                    ->label(__('erp.resources.work_site.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting a contact that areas or work sites still use.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkSiteContacts::route('/'),
        ];
    }
}
