<?php

namespace App\Filament\Widgets\Schemas;

use App\Models\DashboardWidget;
use App\Support\Dashboard\DashboardSource;
use App\Support\Dashboard\DashboardWidgetData;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * The form to build a dashboard block: what (counter, follow-up list, status overview), on which list,
 * with which filters, and how it shows (number or percentage, columns, grouping, size).
 */
class DashboardWidgetForm
{
    /**
     * @return array<int, mixed>
     */
    public static function schema(string $panel): array
    {
        $source = fn (Get $get): ?DashboardSource => DashboardSource::find($panel, $get('source'));

        return [
            Grid::make(2)->schema([
                TextInput::make('title')
                    ->label(__('erp.dashboard.builder.title'))
                    ->required()
                    ->maxLength(100),
                Select::make('source')
                    ->label(__('erp.dashboard.builder.source'))
                    ->helperText(__('erp.dashboard.builder.source_help'))
                    ->options(fn (): array => DashboardSource::options($panel))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        foreach (['filters' => [], 'base_filters' => [], 'columns' => [], 'group_by' => null, 'sort_field' => null, 'aggregate_field' => null] as $key => $value) {
                            $set($key, $value);
                        }
                    }),
            ]),
            ToggleButtons::make('type')
                ->label(__('erp.dashboard.builder.type'))
                ->options(collect(DashboardWidget::TYPES)->mapWithKeys(fn (string $type): array => [$type => __('erp.dashboard.builder.types.'.$type)])->all())
                ->icons([DashboardWidget::COUNTER => Heroicon::OutlinedHashtag, DashboardWidget::LIST => Heroicon::OutlinedListBullet, DashboardWidget::STATUS => Heroicon::OutlinedChartBar])
                ->default(DashboardWidget::COUNTER)
                ->inline()
                ->required()
                ->live(),
            self::filters('filters', __('erp.dashboard.builder.filters'), __('erp.dashboard.builder.filters_help'), $panel),

            // Counter and status overview: what is counted, and how it shows.
            Section::make(__('erp.dashboard.builder.value'))
                ->compact()
                ->columns(3)
                ->visible(fn (Get $get): bool => in_array($get('type'), [DashboardWidget::COUNTER, DashboardWidget::STATUS], true))
                ->schema([
                    Select::make('group_by')
                        ->label(__('erp.dashboard.builder.group_by'))
                        ->helperText(__('erp.dashboard.builder.group_by_help'))
                        ->options(fn (Get $get): array => $source($get)?->fieldOptions(['enum', 'relation', 'boolean', 'date']) ?? [])
                        ->required(fn (Get $get): bool => $get('type') === DashboardWidget::STATUS)
                        ->visible(fn (Get $get): bool => $get('type') === DashboardWidget::STATUS)
                        ->columnSpanFull(),
                    Select::make('aggregate')
                        ->label(__('erp.dashboard.builder.aggregate'))
                        ->options(collect(DashboardWidgetData::AGGREGATES)->mapWithKeys(fn (string $key): array => [$key => __('erp.dashboard.builder.aggregates.'.$key)])->all())
                        ->default('count')
                        ->selectablePlaceholder(false)
                        ->live(),
                    Select::make('aggregate_field')
                        ->label(__('erp.dashboard.builder.aggregate_field'))
                        ->options(fn (Get $get): array => $source($get)?->fieldOptions(['number']) ?? [])
                        ->required(fn (Get $get): bool => in_array($get('aggregate'), ['sum', 'avg'], true))
                        ->visible(fn (Get $get): bool => in_array($get('aggregate'), ['sum', 'avg'], true)),
                    ToggleButtons::make('display')
                        ->label(__('erp.dashboard.builder.display'))
                        ->options(collect(DashboardWidgetData::DISPLAYS)->mapWithKeys(fn (string $key): array => [$key => __('erp.dashboard.builder.displays.'.$key)])->all())
                        ->default('number')
                        ->inline()
                        ->live(),
                    Select::make('color')
                        ->label(__('erp.dashboard.builder.color'))
                        ->options(collect(['primary', 'success', 'warning', 'danger', 'info', 'gray'])->mapWithKeys(fn (string $color): array => [$color => __('erp.dashboard.builder.colors.'.$color)])->all())
                        ->default('primary')
                        ->selectablePlaceholder(false)
                        ->visible(fn (Get $get): bool => $get('type') === DashboardWidget::COUNTER),
                ]),
            self::filters('base_filters', __('erp.dashboard.builder.base_filters'), __('erp.dashboard.builder.base_filters_help'), $panel)
                ->visible(fn (Get $get): bool => $get('type') === DashboardWidget::COUNTER && in_array($get('display'), ['percentage', 'both'], true)),

            // Follow-up list: columns, order and how many.
            Section::make(__('erp.dashboard.builder.list'))
                ->compact()
                ->columns(3)
                ->visible(fn (Get $get): bool => $get('type') === DashboardWidget::LIST)
                ->schema([
                    Select::make('columns')
                        ->label(__('erp.dashboard.builder.columns'))
                        ->helperText(__('erp.dashboard.builder.columns_help'))
                        ->options(fn (Get $get): array => $source($get)?->fieldOptions() ?? [])
                        ->multiple()
                        ->maxItems(5)
                        ->columnSpanFull(),
                    Select::make('sort_field')
                        ->label(__('erp.dashboard.builder.sort_field'))
                        ->options(fn (Get $get): array => $source($get)?->fieldOptions() ?? []),
                    ToggleButtons::make('sort_direction')
                        ->label(__('erp.dashboard.builder.sort_direction'))
                        ->options(['asc' => __('erp.dashboard.builder.asc'), 'desc' => __('erp.dashboard.builder.desc')])
                        ->default('asc')
                        ->inline(),
                    TextInput::make('limit')
                        ->label(__('erp.dashboard.builder.limit'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(10),
                ]),
            Grid::make(2)->schema([
                ToggleButtons::make('column_span')
                    ->label(__('erp.dashboard.builder.column_span'))
                    ->options(collect(DashboardWidget::SPANS)->mapWithKeys(fn (string $span): array => [$span => __('erp.dashboard.builder.spans.'.$span)])->all())
                    ->default('1')
                    ->inline()
                    ->required(),
                Toggle::make('is_shared')
                    ->label(__('erp.dashboard.builder.is_shared'))
                    ->helperText(__('erp.dashboard.builder.is_shared_help'))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ]),
        ];
    }

    /**
     * A list of conditions: field, operator and its value (values, a number of days, a number or text).
     */
    public static function filters(string $name, string $label, string $help, string $panel): Repeater
    {
        $source = fn (Get $get): ?DashboardSource => DashboardSource::find($panel, $get('../../source'));
        $type = fn (Get $get): ?string => $source($get)?->fields()[$get('field')]['type'] ?? null;

        return Repeater::make($name)
            ->label($label)
            ->helperText($help)
            ->addActionLabel(__('erp.dashboard.builder.add_filter'))
            ->defaultItems(0)
            ->columns(3)
            ->visible(fn (Get $get): bool => filled($get('source')))
            ->schema([
                Select::make('field')
                    ->label(__('erp.dashboard.builder.field'))
                    ->options(fn (Get $get): array => $source($get)?->fieldOptions() ?? [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('operator', null)),
                Select::make('operator')
                    ->label(__('erp.dashboard.builder.operator'))
                    ->options(fn (Get $get): array => DashboardSource::operators($type($get)))
                    ->required()
                    ->live(),
                Select::make('value')
                    ->label(__('erp.dashboard.builder.values'))
                    ->options(fn (Get $get): array => $source($get)?->valueOptions((string) $get('field')) ?? [])
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => in_array($get('operator'), ['in', 'not_in'], true)),
                TextInput::make('days')
                    ->label(__('erp.dashboard.builder.days'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(3650)
                    ->required()
                    ->visible(fn (Get $get): bool => in_array($get('operator'), ['next_days', 'last_days', 'older_than'], true)),
                TextInput::make('number')
                    ->label(__('erp.dashboard.builder.number'))
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get): bool => in_array($get('operator'), ['eq', 'gt', 'gte', 'lt', 'lte'], true)),
                TextInput::make('text')
                    ->label(__('erp.dashboard.builder.text'))
                    ->maxLength(100)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('operator') === 'contains'),
            ]);
    }

    /**
     * The form state of a block.
     *
     * @return array<string, mixed>
     */
    public static function fill(DashboardWidget $widget): array
    {
        return [
            'title' => $widget->title,
            'source' => $widget->source,
            'type' => $widget->type,
            'column_span' => $widget->column_span,
            'is_shared' => $widget->is_shared,
            ...(array) $widget->config,
        ];
    }

    /**
     * The block's attributes from the form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data, bool $canShare): array
    {
        $config = collect($data)->only(['filters', 'base_filters', 'aggregate', 'aggregate_field', 'display', 'color', 'group_by', 'columns', 'sort_field', 'sort_direction', 'limit'])
            ->map(fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value)
            ->all();

        return [
            'title' => (string) $data['title'],
            'source' => (string) $data['source'],
            'type' => in_array($data['type'] ?? null, DashboardWidget::TYPES, true) ? $data['type'] : DashboardWidget::COUNTER,
            'column_span' => in_array((string) ($data['column_span'] ?? '1'), DashboardWidget::SPANS, true) ? (string) $data['column_span'] : '1',
            'config' => $config,
            ...($canShare ? ['is_shared' => (bool) ($data['is_shared'] ?? false)] : []),
        ];
    }
}
