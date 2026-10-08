<?php

namespace App\Filament\Resources\WorkSites\Schemas;

use App\Enums\BuildingType;
use App\Models\Country;
use App\Models\WorkSite;
use App\Models\WorkSiteContact;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkSiteForm
{
    /**
     * Belgian provinces, offered as suggestions; any other region can be typed.
     */
    public const PROVINCES = [
        'Antwerpen', 'Limburg', 'Oost-Vlaanderen', 'Vlaams-Brabant', 'West-Vlaanderen',
        'Brabant wallon', 'Hainaut', 'Liège', 'Luxembourg', 'Namur', 'Brussel / Bruxelles',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.work_site'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextInput::make('cow_code')
                                    ->label(__('erp.fields.cow_code'))
                                    ->helperText(__('erp.help.cow_code'))
                                    ->placeholder('02GAM')
                                    ->required()
                                    ->maxLength(5)
                                    ->regex(WorkSite::COW_CODE_PATTERN)
                                    ->validationMessages(['regex' => __('erp.help.cow_code_invalid')])
                                    ->unique(ignoreRecord: true)
                                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono); font-weight: 700;']),
                                // Only set here for a new site; afterwards it changes through the "change building type" form, which keeps the log.
                                Select::make('building_type')
                                    ->label(__('erp.fields.building_type'))
                                    ->options(BuildingType::class)
                                    ->required()
                                    ->visibleOn('create'),
                                Select::make('work_site_area_id')
                                    ->label(__('erp.resources.work_site_area.singular'))
                                    ->relationship('area', 'name')
                                    ->searchable()
                                    ->preload(),
                                Select::make('work_site_contact_id')
                                    ->label(__('erp.fields.contact_person'))
                                    ->helperText(__('erp.help.work_site_contact'))
                                    ->relationship('contact', 'last_name')
                                    ->getOptionLabelFromRecordUsing(fn (WorkSiteContact $record): string => $record->label())
                                    ->searchable(['first_name', 'last_name', 'company'])
                                    ->preload()
                                    ->createOptionForm(WorkSiteContactFields::make()),
                            ]),
                        Section::make(__('erp.fields.address'))
                            ->columnSpan(['lg' => 2])
                            ->columns(6)
                            ->schema([
                                TextInput::make('street')
                                    ->label(__('erp.fields.street_only'))
                                    ->maxLength(150)
                                    ->columnSpan(4),
                                TextInput::make('house_number')
                                    ->label(__('erp.fields.house_number'))
                                    ->maxLength(20)
                                    ->columnSpan(1),
                                TextInput::make('addition')
                                    ->label(__('erp.fields.addition'))
                                    ->placeholder(__('erp.help.addition'))
                                    ->maxLength(20)
                                    ->columnSpan(1),
                                TextInput::make('postal_code')
                                    ->label(__('erp.fields.postal_code'))
                                    ->maxLength(10)
                                    ->columnSpan(2),
                                TextInput::make('city')
                                    ->label(__('erp.fields.city'))
                                    ->maxLength(100)
                                    ->columnSpan(4),
                                TextInput::make('state')
                                    ->label(__('erp.fields.state'))
                                    ->datalist(self::PROVINCES)
                                    ->maxLength(100)
                                    ->columnSpan(3),
                                Select::make('country_id')
                                    ->label(__('erp.fields.country'))
                                    ->options(fn (): array => Country::options())
                                    ->default(fn (): ?int => Country::belgiumId())
                                    ->searchable()
                                    ->columnSpan(3),
                            ]),
                    ]),
                Section::make(__('erp.sections.pictures'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        FileUpload::make('images')
                            ->hiddenLabel()
                            ->helperText(__('erp.help.work_site_images'))
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(20)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(8192)
                            ->disk('public')
                            ->directory('work-sites')
                            ->visibility('public')
                            ->panelLayout('grid')
                            ->imageEditor(),
                    ]),
            ]);
    }
}
