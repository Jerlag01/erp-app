<?php

namespace App\Filament\Resources\Trainings;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Trainings\Pages\ManageTrainings;
use App\Models\Training;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Trainings in the planning module. Participants cannot be planned on training days, and a training
 * day counts as 8 hours on their prestation sheet.
 */
class TrainingResource extends Resource
{
    protected static ?string $model = Training::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'trainings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Planning;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.trainings.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.trainings.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                TextInput::make('name')->label(__('erp.trainings.name'))->placeholder('VCA VOL, BA5, hoogwerker…')->required()->maxLength(255)->columnSpan(4),
                DatePicker::make('start_date')
                    ->label(__('erp.trainings.start_date'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => blank($get('end_date')) || $get('end_date') < $state ? $set('end_date', $state) : null)
                    ->columnSpan(1),
                DatePicker::make('end_date')->label(__('erp.trainings.end_date'))->required()->afterOrEqual('start_date')->columnSpan(1),
                Select::make('participants')
                    ->label(__('erp.trainings.participants'))
                    ->relationship('participants', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),
                Section::make(__('erp.trainings.location'))
                    ->columnSpanFull()
                    ->columns(6)
                    ->schema([
                        TextInput::make('company')->label(__('erp.trainings.company'))->maxLength(255)->columnSpan(6),
                        TextInput::make('street')->label(__('erp.fields.street_only'))->maxLength(150)->columnSpan(3),
                        TextInput::make('house_number')->label(__('erp.fields.house_number'))->maxLength(20)->columnSpan(1),
                        TextInput::make('addition')->label(__('erp.fields.addition'))->maxLength(20)->columnSpan(2),
                        TextInput::make('postal_code')->label(__('erp.fields.postal_code'))->maxLength(20)->columnSpan(2),
                        TextInput::make('city')->label(__('erp.fields.city'))->maxLength(100)->columnSpan(4),
                        TextInput::make('contact_phone')->label(__('erp.fields.phone'))->tel()->maxLength(50)->columnSpan(3),
                        TextInput::make('contact_email')->label(__('erp.fields.email'))->email()->maxLength(255)->columnSpan(3),
                    ]),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('participants'))
            ->columns([
                TextColumn::make('name')->label(__('erp.trainings.name'))->weight(FontWeight::Bold)->description(fn (Training $record): ?string => $record->company)->searchable(['name', 'company']),
                TextColumn::make('start_date')
                    ->label(__('erp.trainings.dates'))
                    ->state(fn (Training $record): string => $record->start_date->equalTo($record->end_date) ? $record->start_date->format('d/m/Y') : $record->start_date->format('d/m/Y').' → '.$record->end_date->format('d/m/Y'))
                    ->sortable(),
                TextColumn::make('address')->label(__('erp.trainings.location'))->state(fn (Training $record): ?string => $record->addressLine())->placeholder('—')->wrap(),
                TextColumn::make('contact')->label(__('erp.trainings.contact'))->state(fn (Training $record): ?string => implode(' · ', array_filter([$record->contact_phone, $record->contact_email])) ?: null)->placeholder('—')->toggleable(),
                TextColumn::make('participants.name')->label(__('erp.trainings.participants'))->badge()->color('gray')->limitList(4),
            ])
            ->filters([
                Filter::make('upcoming')->label(__('erp.trainings.upcoming'))->query(fn (Builder $query) => $query->whereDate('end_date', '>=', today()))->default(),
            ])
            ->recordActions([
                EditAction::make()->after(fn (Training $record) => ManageTrainings::notifyParticipants($record, updated: true)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrainings::route('/'),
        ];
    }
}
