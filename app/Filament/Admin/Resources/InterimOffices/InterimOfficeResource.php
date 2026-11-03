<?php

namespace App\Filament\Admin\Resources\InterimOffices;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\InterimOffices\Pages\ManageInterimOffices;
use App\Models\InterimOffice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The interim offices with their contacts (name and e-mail). Interim employees are linked to an office;
 * its contacts get the weekly prestation sheets of those employees.
 */
class InterimOfficeResource extends Resource
{
    protected static ?string $model = InterimOffice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return modules()->employees() && parent::canAccess();
    }

    public static function getModelLabel(): string
    {
        return __('erp.interim_offices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.interim_offices.plural');
    }

    /**
     * Also used to add an office from the employee form.
     *
     * @return list<Component|TextInput|Toggle|Repeater>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')
                ->label(__('erp.fields.name'))
                ->placeholder('Randstad, Adecco, Start People…')
                ->required()
                ->maxLength(150)
                ->unique(InterimOffice::class, 'name', ignoreRecord: true),
            Toggle::make('is_active')->label(__('erp.fields.active'))->default(true)->inline(false),
            Repeater::make('contacts')
                ->label(__('erp.interim_offices.contacts'))
                ->helperText(__('erp.interim_offices.contacts_help'))
                ->relationship()
                ->orderColumn('sort')
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel(__('erp.interim_offices.add_contact'))
                ->schema([
                    TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(150),
                    TextInput::make('email')->label(__('erp.fields.email'))->email()->required()->maxLength(190),
                ])
                ->columnSpanFull(),
            TextInput::make('notes')->label(__('erp.fields.notes'))->maxLength(500)->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('contacts')->withCount(['employees' => fn (Builder $query) => $query->where('is_interim', true)->whereNull('left_on')]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight('bold')->searchable()->sortable(),
                TextColumn::make('contacts')
                    ->label(__('erp.interim_offices.contacts'))
                    ->state(fn (InterimOffice $record): array => $record->contacts->map(fn ($contact): string => $contact->name.' <'.$contact->email.'>')->all())
                    ->listWithLineBreaks()
                    ->placeholder(__('erp.interim_offices.no_contacts')),
                TextColumn::make('employees_count')->label(__('erp.interim_offices.employees'))->alignEnd()->sortable(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInterimOffices::route('/'),
        ];
    }
}
