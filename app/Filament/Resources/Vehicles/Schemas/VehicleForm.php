<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.vehicle'))
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                TextInput::make('plate_number')
                                    ->label(__('erp.fields.plate_number'))
                                    ->placeholder('1-ABC-123')
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                                TextInput::make('vin')
                                    ->label(__('erp.fields.vin'))
                                    ->helperText(__('erp.help.vin'))
                                    ->required()
                                    ->length(17)
                                    ->regex('/^[A-HJ-NPR-Za-hj-npr-z0-9]{17}$/')
                                    ->unique(ignoreRecord: true)
                                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(preg_replace('/\s+/', '', (string) $state)))
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                                TextInput::make('brand')
                                    ->label(__('erp.fields.brand'))
                                    ->placeholder('Volkswagen')
                                    ->required()
                                    ->maxLength(60)
                                    ->datalist(['Citroën', 'Fiat', 'Ford', 'Iveco', 'Mercedes-Benz', 'Opel', 'Peugeot', 'Renault', 'Toyota', 'Volkswagen']),
                                TextInput::make('type')
                                    ->label(__('erp.fields.type'))
                                    ->placeholder('Transporter T6.1')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('year')
                                    ->label(__('erp.fields.year'))
                                    ->numeric()
                                    ->minValue(1950)
                                    ->maxValue((int) date('Y') + 1),
                                Select::make('fuel')
                                    ->label(__('erp.fields.fuel'))
                                    ->options(FuelType::class),
                                TextInput::make('mileage')
                                    ->label(__('erp.fields.mileage'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('km'),
                                Select::make('status')
                                    ->label(__('erp.fields.status'))
                                    ->options(VehicleStatus::class)
                                    ->default(VehicleStatus::Active)
                                    ->required()
                                    ->selectablePlaceholder(false),
                            ]),
                        Grid::make(1)
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Section::make(__('erp.sections.control'))
                                    ->description(__('erp.help.control'))
                                    ->schema([
                                        DatePicker::make('control_date')
                                            ->label(__('erp.fields.control_date'))
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->maxDate(today())
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                // Belgian periodic inspection is yearly for vans; suggest the next date.
                                                if ($state && blank($get('next_control_date'))) {
                                                    $set('next_control_date', Carbon::parse($state)->addYear()->toDateString());
                                                }
                                            }),
                                        DatePicker::make('next_control_date')
                                            ->label(__('erp.fields.next_control_date'))
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->afterOrEqual('control_date'),
                                    ]),
                                Section::make(__('erp.sections.assignment'))
                                    ->schema([
                                        Select::make('location_id')
                                            ->label(__('erp.resources.location.singular'))
                                            ->relationship('location', 'name', fn (Builder $query) => $query->where('is_active', true)->visibleTo(auth()->user()))
                                            ->preload()
                                            ->visible(fn (): bool => modules()->locations()),
                                        Select::make('team_id')
                                            ->label(__('erp.resources.team.singular'))
                                            ->relationship('team', 'name', fn (Builder $query) => $query->where('is_active', true)->visibleTo(auth()->user()))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (): bool => modules()->teams()),
                                        Select::make('driver_id')
                                            ->label(__('erp.fields.driver'))
                                            ->relationship('driver', 'name', fn (Builder $query) => $query->where('is_active', true))
                                            ->searchable()
                                            ->preload(),
                                    ]),
                            ]),
                    ]),
                Section::make(__('erp.sections.extra'))
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('photo')
                            ->label(__('erp.fields.photo'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(4096)
                            ->disk('public')
                            ->directory('vehicles')
                            ->visibility('public')
                            ->imageEditor(),
                        Textarea::make('notes')
                            ->label(__('erp.fields.notes'))
                            ->rows(6),
                    ]),
            ]);
    }
}
