<?php

namespace App\Filament\App\Pages;

use App\Actions\ReportIncident as ReportIncidentAction;
use App\Enums\IncidentType;
use App\Enums\NavigationGroup;
use App\Models\Incident;
use App\Models\Location;
use App\Models\WorkSite;
use App\Support\PrivatePhotos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * The incident form for employees. Reporter, date and time are filled in by the system.
 *
 * @property-read Schema $form
 */
class ReportIncident extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Safety;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'report-incident';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && Gate::allows('create', Incident::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.incidents.report');
    }

    public function getTitle(): string
    {
        return __('erp.incidents.report');
    }

    public function mount(): void
    {
        $this->form->fill(['place_type' => $this->placeTypes() === [] ? null : array_key_first($this->placeTypes())]);
    }

    /**
     * The kinds of place that can be chosen, depending on the switched-on modules.
     *
     * @return array<string, string>
     */
    protected function placeTypes(): array
    {
        return array_filter([
            'location' => modules()->locations() ? __('erp.incidents.place_types.location') : null,
            'work_site' => modules()->workSites() ? __('erp.incidents.place_types.work_site') : null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $placeTypes = $this->placeTypes();

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Text::make(fn (): string => __('erp.incidents.reported_by', ['name' => auth()->user()->name]))
                            ->icon(Heroicon::OutlinedUser),
                        Text::make(fn (): string => __('erp.incidents.automatic_time'))
                            ->icon(Heroicon::OutlinedClock),
                    ]),
                Section::make(__('erp.incidents.what'))
                    ->schema([
                        ToggleButtons::make('type')
                            ->label(__('erp.incidents.type'))
                            ->options(IncidentType::class)
                            ->icons([
                                IncidentType::Accident->value => 'heroicon-o-exclamation-triangle',
                                IncidentType::NearMiss->value => 'heroicon-o-hand-raised',
                                IncidentType::DangerousSituation->value => 'heroicon-o-eye',
                                IncidentType::DangerousAction->value => 'heroicon-o-no-symbol',
                            ])
                            ->inline()
                            ->required(),
                    ]),
                Section::make(__('erp.incidents.where'))
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('place_type')
                            ->label(__('erp.incidents.place_type'))
                            ->options($placeTypes)
                            ->inline()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('place_id', null))
                            ->required($placeTypes !== [])
                            ->visible($placeTypes !== [])
                            ->columnSpanFull(),
                        Select::make('place_id')
                            ->label(fn (Get $get): string => $get('place_type') === 'work_site' ? __('erp.resources.work_site.singular') : __('erp.resources.location.singular'))
                            ->options(fn (Get $get): array => match ($get('place_type')) {
                                'location' => Location::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
                                'work_site' => WorkSite::query()->orderBy('cow_code')->get()
                                    ->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' – '.implode(' ', array_filter([$site->street, $site->house_number, $site->city])), ' –')])
                                    ->all(),
                                default => [],
                            })
                            ->searchable()
                            ->required(fn (Get $get): bool => filled($get('place_type')))
                            ->visible(fn (Get $get): bool => filled($get('place_type'))),
                        TextInput::make('location_details')
                            ->label(__('erp.incidents.location_details'))
                            ->placeholder(__('erp.incidents.location_details_placeholder'))
                            ->required($placeTypes === [])
                            ->maxLength(255),
                    ]),
                Section::make(__('erp.incidents.consequences'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('other_victims')
                            ->label(__('erp.incidents.other_victims'))
                            ->live(),
                        Toggle::make('material_damage')
                            ->label(__('erp.incidents.material_damage'))
                            ->live(),
                        Textarea::make('other_victims_details')
                            ->label(__('erp.incidents.other_victims_details'))
                            ->rows(2)
                            ->visible(fn (Get $get): bool => (bool) $get('other_victims')),
                        Textarea::make('material_damage_details')
                            ->label(__('erp.incidents.material_damage_details'))
                            ->rows(2)
                            ->visible(fn (Get $get): bool => (bool) $get('material_damage')),
                    ]),
                Section::make(__('erp.incidents.description'))
                    ->schema([
                        Textarea::make('description')
                            ->hiddenLabel()
                            ->placeholder(__('erp.incidents.description_placeholder'))
                            ->rows(6)
                            ->required()
                            ->maxLength(10000),
                        PrivatePhotos::upload('photos', 'incidents')
                            ->label(__('erp.incidents.photos'))
                            ->helperText(__('erp.incidents.photos_help')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('submit')
                    ->footer([
                        Actions::make([
                            Action::make('submit')
                                ->label(__('erp.incidents.submit'))
                                ->icon(Heroicon::OutlinedPaperAirplane)
                                ->submit('submit'),
                        ]),
                    ]),
                View::make('filament.app.my-incidents'),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        app(ReportIncidentAction::class)->handle(auth()->user(), $data);

        Notification::make()
            ->title(__('erp.incidents.thanks'))
            ->body(__('erp.incidents.thanks_body'))
            ->success()
            ->send();

        $this->form->fill(['place_type' => $data['place_type'] ?? null]);
    }
}
