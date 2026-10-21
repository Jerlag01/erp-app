<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Models\ChargingStation;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Several charging stations at once on the same COW site (or third-party address): the location and
 * the status are entered once, the stations one per row.
 *
 * @property-read Schema $form
 */
class CreateChargingStations extends Page
{
    protected static string $resource = ChargingStationResource::class;

    /** Most rows added in one go. */
    public const MAX = 50;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return ChargingStationResource::canCreate();
    }

    public function getTitle(): string
    {
        return __('erp.charging.create_multiple');
    }

    public function getSubheading(): ?string
    {
        return __('erp.charging.create_multiple_help');
    }

    /**
     * Opened from a 608 project (?project=): that project and its location are filled in.
     */
    public function mount(): void
    {
        $this->form->fill();

        $project = Project::query()->find(request()->integer('project'));

        if ($project && array_key_exists($project->id, ChargingStationResource::projectOptions())) {
            $this->form->fill([...$this->data, ...ChargingStationResource::projectDefaults($project)]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                ChargingStationResource::locationSection(),
                Section::make(__('erp.charging.sections.stations'))
                    ->description(__('erp.charging.stations_rows_help'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('stations')
                            ->hiddenLabel()
                            ->schema([
                                ...ChargingStationResource::stationFields(unique: fn (TextInput $field): TextInput => $field->unique('charging_stations', 'ace_number')->distinct()),
                                ...(ChargingStationResource::canSeeSecrets() ? ChargingStationResource::secretFields() : []),
                            ])
                            ->columns(4)
                            ->defaultItems(2)
                            ->minItems(1)
                            ->maxItems(self::MAX)
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => filled($state['ace_number'] ?? null) ? $state['ace_number'] : null)
                            ->addActionLabel(__('erp.charging.add_row')),
                    ]),
                ChargingStationResource::statusSection()->description(__('erp.charging.status_for_all')),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('create')
                ->footer([
                    Actions::make([
                        Action::make('create')->label(__('erp.charging.create_multiple_submit'))->submit('create'),
                        Action::make('cancel')->label(__('filament-panels::resources/pages/create-record.form.actions.cancel.label'))->color('gray')->url(ChargingStationResource::getUrl()),
                    ]),
                ]),
        ]);
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $rows = array_values((array) ($data['stations'] ?? []));
        unset($data['stations']);

        $stations = DB::transaction(fn (): array => array_map(
            fn (array $row): ChargingStation => ChargingStation::query()->create([...$data, ...$row]),
            $rows,
        ));

        Notification::make()->title(__('erp.charging.created_multiple', ['count' => count($stations)]))->success()->send();

        $this->redirect(ChargingStationResource::getUrl('index'));
    }
}
