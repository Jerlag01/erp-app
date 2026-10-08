<?php

namespace App\Filament\Resources\ItManufacturers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ItManufacturers\Pages\CreateItManufacturer;
use App\Filament\Resources\ItManufacturers\Pages\EditItManufacturer;
use App\Filament\Resources\ItManufacturers\Pages\ListItManufacturers;
use App\Filament\Schemas\CompanyFields;
use App\Models\ItManufacturer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The IT module's own list, kept apart from the stock module on purpose.
 */
class ItManufacturerResource extends Resource
{
    protected static ?string $model = ItManufacturer::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-manufacturers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_manufacturer.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_manufacturer.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...CompanyFields::make('it-manufacturers', []),
            Section::make(__('erp.sections.it_support'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('support_url')
                            ->label(__('erp.fields.support_url'))
                            ->url()
                            ->rule('url:http,https')
                            ->maxLength(255),
                        TextInput::make('support_email')
                            ->label(__('erp.fields.support_email'))
                            ->email()
                            ->maxLength(150),
                        TextInput::make('support_phone')
                            ->label(__('erp.fields.support_phone'))
                            ->tel()
                            ->maxLength(30),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['models', 'licenses', 'items']))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(32),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->description(fn (ItManufacturer $record): ?string => $record->website)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('support_phone')
                    ->label(__('erp.fields.support_phone'))
                    ->description(fn (ItManufacturer $record): ?string => $record->support_email)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('models_count')
                    ->label(__('erp.resources.it_model.plural'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('licenses_count')
                    ->label(__('erp.resources.it_license.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting entries that IT records still use.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItManufacturers::route('/'),
            'create' => CreateItManufacturer::route('/create'),
            'edit' => EditItManufacturer::route('/{record}/edit'),
        ];
    }
}
