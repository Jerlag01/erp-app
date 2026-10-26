<?php

namespace App\Filament\Resources\Projects\Actions;

use App\Actions\Interventions\CloseIntervention;
use App\Enums\InterventionCategoryKind;
use App\Enums\MaterialDirection;
use App\Filament\Resources\Projects\RelationManagers\PartsRelationManager;
use App\Models\InterventionCategory;
use App\Models\InterventionReportPosition;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Interventions;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * "Close intervention": the employee fills in everything about the work done. Afterwards the
 * intervention waits for review by an administrator.
 */
class CloseInterventionAction
{
    public static function make(): Action
    {
        return Action::make('closeIntervention')
            ->label(fn (Project $record): string => $record->interventionReport ? __('erp.interventions.close_again') : __('erp.interventions.close'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('success')
            ->visible(fn (Project $record): bool => app(Interventions::class)->canClose(auth()->user(), $record))
            ->authorize(fn (Project $record): bool => app(Interventions::class)->canClose(auth()->user(), $record))
            ->modalHeading(fn (Project $record): string => __('erp.interventions.close_heading', ['reference' => $record->reference]))
            ->modalDescription(__('erp.interventions.close_help'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel(__('erp.interventions.close_submit'))
            ->fillForm(fn (Project $record): array => self::defaults($record))
            ->schema(fn (Project $record): array => self::schema($record))
            ->action(function (Project $record, array $data, Component $livewire): void {
                try {
                    app(CloseIntervention::class)->handle($record, $data, auth()->user());
                } catch (ValidationException $exception) {
                    $path = 'mountedActions.'.(count($livewire->mountedActions) - 1).'.data.';

                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$path.$key => $messages])->all());
                }

                Notification::make()->title(__('erp.interventions.closed'))->body(__('erp.interventions.closed_body'))->success()->send();
                $livewire->redirect(request()->header('Referer') ?: url()->current());
            });
    }

    /**
     * Prefilled from the intervention, or from the previous report after a rejection.
     *
     * @return array<string, mixed>
     */
    public static function defaults(Project $project): array
    {
        $report = $project->interventionReport?->loadMissing(['materials', 'positions', 'coWorkers']);

        return [
            'categories' => $project->interventionCategories()->pluck('intervention_categories.id')->all(),
            'work_site_id' => $report->work_site_id ?? $project->work_site_id,
            'room' => $report?->room,
            'hours_worked' => $report?->hours_worked,
            'remarks' => $report?->remarks,
            'co_workers' => $report?->coWorkers->modelKeys() ?? [],
            'modified_boards' => $report?->boardNames() ?? [],
            // Parts on the project's part list are suggested as the material used.
            'materials' => $report
                ? $report->materials->map(fn ($material): array => ['stock_item_id' => $material->stock_item_id, 'description' => $material->description, 'quantity' => (float) $material->quantity, 'unit_id' => $material->unit_id, 'direction' => $material->direction->value])->all()
                : $project->parts()->get()->map(fn ($part): array => ['stock_item_id' => $part->stock_item_id, 'description' => $part->description, 'quantity' => (float) $part->quantity, 'unit_id' => $part->unit_id, 'direction' => MaterialDirection::Used->value])->all(),
            'positions' => $report?->positions->map(fn (InterventionReportPosition $position): array => $position->only(['board_name', 'current', 'device', 'number', 'supply']))->all() ?? [],
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected static function schema(Project $project): array
    {
        $categories = fn (callable $get): Collection => InterventionCategory::query()->whereKey((array) $get('categories'))->get();
        $directions = fn (callable $get): array => app(Interventions::class)->directions($categories($get));

        return [
            Section::make(__('erp.interventions.sections.what'))
                ->columns(3)
                ->schema([
                    Select::make('categories')
                        ->label(__('erp.interventions.categories'))
                        ->options(fn (): array => InterventionCategory::options(InterventionCategoryKind::Category, $project->interventionCategories()->pluck('intervention_categories.id')->all()))
                        ->multiple()
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    Select::make('work_site_id')
                        ->label(__('erp.interventions.cow_code'))
                        ->options(fn (): array => WorkSite::query()->orderBy('cow_code')->get()->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' · '.$site->city, ' ·')])->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    TextInput::make('room')
                        ->label(__('erp.interventions.room'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('hours_worked')
                        ->label(__('erp.interventions.hours_worked'))
                        ->helperText(__('erp.interventions.hours_help'))
                        ->numeric()
                        ->minValue(0.25)
                        ->maxValue(999)
                        ->step(0.25)
                        ->suffix(__('erp.interventions.hours_suffix'))
                        ->required(),
                    Select::make('co_workers')
                        ->label(__('erp.interventions.co_workers'))
                        ->helperText(__('erp.interventions.co_workers_help'))
                        ->options(fn (): array => User::query()->active()->whereKeyNot(auth()->id())->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
            Section::make(__('erp.interventions.materials'))
                ->description(fn (Get $get): string => __('erp.interventions.materials_help.'.(count($directions($get)) > 1 ? 'both' : ($directions($get)[0] ?? MaterialDirection::Used)->value)))
                ->schema([
                    Repeater::make('materials')
                        ->hiddenLabel()
                        ->columns(12)
                        ->defaultItems(0)
                        ->addActionLabel(__('erp.interventions.add_material'))
                        ->schema([
                            Select::make('stock_item_id')
                                ->label(__('erp.projects.parts.item'))
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => PartsRelationManager::searchItems($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => StockItem::find($value)?->name)
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    if ($item = StockItem::find($state)) {
                                        $set('description', $item->name);
                                        $set('unit_id', $item->unit_id);
                                    }
                                })
                                ->visible(fn (): bool => modules()->stock())
                                ->columnSpan(['default' => 12, 'md' => 4]),
                            TextInput::make('description')
                                ->label(__('erp.fields.description'))
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 12, 'md' => modules()->stock() ? 3 : 7]),
                            TextInput::make('quantity')
                                ->label(__('erp.projects.parts.quantity'))
                                ->numeric()
                                ->minValue(0.001)
                                ->step('any')
                                ->default(1)
                                ->required()
                                ->columnSpan(['default' => 4, 'md' => 1]),
                            Select::make('unit_id')
                                ->label(__('erp.projects.parts.unit'))
                                ->options(fn (): array => Unit::query()->orderBy('name')->pluck('abbreviation', 'id')->all())
                                ->columnSpan(['default' => 4, 'md' => 2]),
                            ToggleButtons::make('direction')
                                ->label(__('erp.interventions.direction'))
                                ->options(fn (Get $get): array => collect($directions(fn (string $path) => $get('../../'.$path)))->mapWithKeys(fn (MaterialDirection $direction): array => [$direction->value => $direction->getLabel()])->all())
                                ->default(fn (Get $get): string => ($directions(fn (string $path) => $get('../../'.$path))[0] ?? MaterialDirection::Used)->value)
                                ->inline()
                                ->required()
                                ->columnSpan(['default' => 4, 'md' => 2]),
                        ]),
                ]),
            Section::make(__('erp.interventions.positions'))
                ->description(__('erp.interventions.positions_help'))
                ->schema([
                    Repeater::make('positions')
                        ->hiddenLabel()
                        ->columns(12)
                        ->defaultItems(0)
                        ->addActionLabel(__('erp.interventions.add_position'))
                        ->schema([
                            TextInput::make('board_name')
                                ->label(__('erp.interventions.board'))
                                ->placeholder('TD-01')
                                ->required()
                                ->maxLength(100)
                                ->columnSpan(['default' => 12, 'md' => 4]),
                            ToggleButtons::make('current')
                                ->label('AC / DC')
                                ->options(array_combine(InterventionReportPosition::CURRENTS, InterventionReportPosition::CURRENTS))
                                ->default('AC')
                                ->inline()
                                ->required()
                                ->columnSpan(['default' => 6, 'md' => 2]),
                            ToggleButtons::make('device')
                                ->label(__('erp.interventions.device'))
                                ->options(['F' => __('erp.interventions.devices.F'), 'Q' => __('erp.interventions.devices.Q')])
                                ->default('Q')
                                ->inline()
                                ->required()
                                ->columnSpan(['default' => 6, 'md' => 3]),
                            TextInput::make('number')
                                ->label(__('erp.interventions.position_number'))
                                ->placeholder('12')
                                ->required()
                                ->maxLength(20)
                                ->columnSpan(['default' => 6, 'md' => 1]),
                            ToggleButtons::make('supply')
                                ->label(__('erp.interventions.supply'))
                                ->options(['A' => 'A', 'B' => 'B'])
                                ->default('A')
                                ->inline()
                                ->required()
                                ->columnSpan(['default' => 6, 'md' => 2]),
                        ]),
                ]),
            Section::make(__('erp.interventions.sections.boards_remarks'))
                ->schema([
                    TagsInput::make('modified_boards')
                        ->label(__('erp.interventions.modified_boards'))
                        ->helperText(__('erp.interventions.modified_boards_help'))
                        ->placeholder('TD-01')
                        ->nestedRecursiveRules(['max:100']),
                    Textarea::make('remarks')
                        ->label(__('erp.interventions.remarks'))
                        ->helperText(__('erp.interventions.remarks_help'))
                        ->rows(3)
                        ->maxLength(5000),
                    Text::make(__('erp.interventions.executed_at_help')),
                ]),
        ];
    }
}
