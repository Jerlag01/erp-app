<?php

namespace App\Support\Dashboard;

use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * One list of the panel (a Filament resource) as the data of a dashboard block. The records are those of
 * the resource's own query, so a block only counts what the user may see in that list. The fields to
 * filter, group, sort and show come from the model's columns and casts:
 *
 *  - enum: the cast enum's cases; boolean; date (date/datetime casts and the timestamps);
 *  - relation: a "…_id" or "…_by" column with its belongsTo relation (to a user: also "me");
 *  - number and text.
 *
 * Hidden, encrypted, JSON and file columns never show, nor secrets (passwords, tokens, PINs, bank
 * details, wages); purchase prices only for those who may see stock prices.
 */
class DashboardSource
{
    /** Columns never offered (name patterns), for everyone. */
    public const DENIED = '/(password|secret|token|api_key|pin_code|^pin$|_pin$|iban|bic$|bank|salary|wage|national_number|registry|rijksregister|social_security|recovery|private_key|two_factor|remember)/i';

    /** Price columns only for those who may see purchase prices (or administrators). */
    public const PRICES = '/(price|cost|purchase|margin)/i';

    /** @var array<string, array<string, array<string, mixed>>> fields per model, per request */
    protected static array $fieldCache = [];

    /**
     * @param  class-string<resource>  $resource
     */
    public function __construct(public string $resource, public string $panel) {}

    public function key(): string
    {
        return $this->resource::getSlug();
    }

    public function label(): string
    {
        return (string) $this->resource::getPluralModelLabel();
    }

    public function canView(): bool
    {
        return (bool) rescue(fn (): bool => $this->resource::canViewAny(), false, false);
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return $this->resource::getModel();
    }

    /**
     * The records the user may see in this list.
     *
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        return $this->resource::getEloquentQuery();
    }

    /**
     * The fields of this list: key => [label, type, column, options?, relation?, related?].
     *
     * @return array<string, array{label: string, type: string, column: string, enum?: class-string, relation?: string, related?: class-string<Model>}>
     */
    public function fields(?User $user = null): array
    {
        $user ??= auth()->user();
        $model = $this->model();
        $fields = self::$fieldCache[$model] ??= $this->discoverFields($model);
        $prices = $user !== null && ($user->isAdmin() || $user->canSeePrices());

        return array_filter($fields, fn (array $field, string $key): bool => $prices || ! preg_match(self::PRICES, $key), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Fields of one type (or several): key => label.
     *
     * @param  list<string>  $types
     * @return array<string, string>
     */
    public function fieldOptions(array $types = []): array
    {
        return collect($this->fields())
            ->filter(fn (array $field): bool => $types === [] || in_array($field['type'], $types, true))
            ->map(fn (array $field): string => $field['label'])
            ->sort()
            ->all();
    }

    /**
     * The values to choose from for an enum or relation field: value => label.
     *
     * @return array<string, string>
     */
    public function valueOptions(string $key): array
    {
        $field = $this->fields()[$key] ?? null;

        return match ($field['type'] ?? null) {
            'enum' => collect($field['enum']::cases())->mapWithKeys(fn (UnitEnum $case): array => [(string) ($case instanceof BackedEnum ? $case->value : $case->name) => self::enumLabel($case)])->all(),
            'relation' => ($field['related'] === User::class ? ['me' => __('erp.dashboard.builder.me')] : [])
                + $field['related']::query()->limit(500)->get()->mapWithKeys(fn (Model $related): array => [(string) $related->getKey() => self::titleOf($related)])->sort()->all(),
            'boolean' => ['1' => __('erp.dashboard.builder.yes'), '0' => __('erp.dashboard.builder.no')],
            default => [],
        };
    }

    /**
     * Applies filters: [{field, operator, value?, days?}], skipping unknown fields and operators.
     *
     * @param  Builder<Model>  $query
     * @param  array<int|string, array<string, mixed>>  $filters
     * @return Builder<Model>
     */
    public function applyFilters(Builder $query, array $filters, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $fields = $this->fields($user);
        $table = $query->getModel()->getTable();

        foreach ($filters as $filter) {
            $field = $fields[$filter['field'] ?? ''] ?? null;

            if ($field === null) {
                continue;
            }

            $column = $table.'.'.$field['column'];
            $values = array_values(array_filter((array) ($filter['value'] ?? []), fn (mixed $value): bool => $value !== null && $value !== ''));
            $days = max(0, (int) ($filter['days'] ?? 0));
            $number = $filter['number'] ?? null;
            $today = CarbonImmutable::today();

            // "me" in a relation to users: the signed-in user.
            $values = array_map(fn (mixed $value): mixed => $value === 'me' ? $user?->id : $value, $values);

            match ($filter['operator'] ?? '') {
                'in' => $values === [] ? null : $query->whereIn($column, $values),
                'not_in' => $values === [] ? null : $query->where(fn (Builder $query) => $query->whereNotIn($column, $values)->orWhereNull($column)),
                'empty' => $query->where(fn (Builder $query) => $field['type'] === 'text' ? $query->whereNull($column)->orWhere($column, '') : $query->whereNull($column)),
                'not_empty' => $query->where(fn (Builder $query) => $field['type'] === 'text' ? $query->whereNotNull($column)->where($column, '!=', '') : $query->whereNotNull($column)),
                'is_true' => $query->where($column, true),
                'is_false' => $query->where(fn (Builder $query) => $query->where($column, false)->orWhereNull($column)),
                'overdue' => $query->whereDate($column, '<', $today),
                'today' => $query->whereDate($column, $today),
                'next_days' => $query->whereDate($column, '>=', $today)->whereDate($column, '<=', $today->addDays($days)),
                'last_days' => $query->whereDate($column, '>=', $today->subDays($days))->whereDate($column, '<=', $today),
                'older_than' => $query->whereDate($column, '<', $today->subDays($days)),
                'this_week' => $query->whereBetween($column, [$today->startOfWeek(), $today->endOfWeek()]),
                'this_month' => $query->whereBetween($column, [$today->startOfMonth(), $today->endOfMonth()]),
                'this_year' => $query->whereBetween($column, [$today->startOfYear(), $today->endOfYear()]),
                'eq', 'gt', 'lt', 'gte', 'lte' => is_numeric($number) ? $query->where($column, ['eq' => '=', 'gt' => '>', 'lt' => '<', 'gte' => '>=', 'lte' => '<='][$filter['operator']], (float) $number) : null,
                'contains' => filled($filter['text'] ?? null) ? $query->where($column, 'like', '%'.addcslashes((string) $filter['text'], '%_\\').'%') : null,
                default => null,
            };
        }

        return $query;
    }

    /**
     * The operators of a field type: key => label.
     *
     * @return array<string, string>
     */
    public static function operators(?string $type): array
    {
        $keys = match ($type) {
            'enum', 'relation' => ['in', 'not_in', 'empty', 'not_empty'],
            'boolean' => ['is_true', 'is_false'],
            'date' => ['overdue', 'today', 'next_days', 'last_days', 'older_than', 'this_week', 'this_month', 'this_year', 'empty', 'not_empty'],
            'number' => ['eq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'],
            'text' => ['contains', 'empty', 'not_empty'],
            default => [],
        };

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => __('erp.dashboard.builder.operators.'.$key)])->all();
    }

    /**
     * A record's value of a field, as text for a list.
     */
    public function display(Model $record, string $key): string
    {
        $field = $this->fields()[$key] ?? null;

        if ($field === null) {
            return '';
        }

        $value = $record->getAttribute($field['column']);

        return match (true) {
            $value === null || $value === '' => '—',
            $field['type'] === 'relation' => self::titleOf($record->{$field['relation']}),
            $value instanceof UnitEnum => self::enumLabel($value),
            $field['type'] === 'enum' => self::enumLabel($field['enum']::tryFrom($value)) ?: (string) $value,
            $field['type'] === 'boolean' => $value ? '✓' : '—',
            $value instanceof \DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i'),
            $field['type'] === 'date' => rescue(fn (): string => CarbonImmutable::parse($value)->format('d/m/Y'), (string) $value, false),
            $field['type'] === 'number' => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ','),
            default => Str::limit((string) $value, 80),
        };
    }

    /**
     * The label of a group value (status overview).
     */
    public function groupLabel(string $key, mixed $value): string
    {
        $field = $this->fields()[$key] ?? null;

        if ($value === null || $value === '') {
            return __('erp.dashboard.builder.none');
        }

        return match ($field['type'] ?? null) {
            'enum' => self::enumLabel($field['enum']::tryFrom($value)) ?: (string) $value,
            'boolean' => $value ? __('erp.dashboard.builder.yes') : __('erp.dashboard.builder.no'),
            'relation' => ($related = $field['related']::query()->find($value)) ? self::titleOf($related) : '#'.$value,
            default => (string) $value,
        };
    }

    /**
     * Where a record opens: its view page, else its edit page, when the user may.
     */
    public function recordUrl(Model $record): ?string
    {
        foreach (['view' => 'view', 'edit' => 'update'] as $page => $ability) {
            if ($this->resource::hasPage($page) && rescue(fn (): bool => $this->resource::can($ability, $record), false, false)) {
                return rescue(fn (): string => $this->resource::getUrl($page, ['record' => $record], panel: $this->panel), null, false);
            }
        }

        return null;
    }

    public function indexUrl(): ?string
    {
        return rescue(fn (): string => $this->resource::getUrl(panel: $this->panel), null, false);
    }

    public function recordTitle(Model $record): string
    {
        return (string) rescue(fn (): string => (string) $this->resource::getRecordTitle($record), '#'.$record->getKey(), false);
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, array<string, mixed>>
     */
    protected function discoverFields(string $model): array
    {
        /** @var Model $instance */
        $instance = new $model;
        $casts = $instance->getCasts();
        $hidden = $instance->getHidden();
        $fields = [];

        try {
            $columns = collect(Schema::getColumns($instance->getTable()))->keyBy('name');
        } catch (Throwable) {
            return [];
        }

        foreach ($columns as $name => $column) {
            $cast = is_string($casts[$name] ?? null) ? $casts[$name] : null;
            $castBase = $cast !== null ? Str::before($cast, ':') : null;

            if ($name === $instance->getKeyName() || in_array($name, $hidden, true) || preg_match(self::DENIED, $name)
                || in_array($castBase, ['array', 'json', 'object', 'collection', 'encrypted', 'encrypted:array', 'hashed', 'encrypted:collection', 'encrypted:object', 'encrypted:json', 'AsArrayObject', 'AsCollection'], true)
                || str_starts_with((string) $castBase, 'encrypted') || preg_match('/(_path|^path|image|photo|file|signature|attachments|images)$/', $name)) {
                continue;
            }

            $typeName = strtolower((string) ($column['type_name'] ?? ''));
            $label = Lang::has('erp.fields.'.$name) && is_string(__('erp.fields.'.$name)) ? __('erp.fields.'.$name) : Str::headline(Str::replaceLast('_id', '', $name));

            if ($cast !== null && enum_exists($cast)) {
                $fields[$name] = ['label' => $label, 'type' => 'enum', 'column' => $name, 'enum' => $cast];
            } elseif (in_array($castBase, ['bool', 'boolean'], true) || $typeName === 'tinyint' && str_starts_with($name, 'is_')) {
                $fields[$name] = ['label' => $label, 'type' => 'boolean', 'column' => $name];
            } elseif (in_array($castBase, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) || in_array($typeName, ['date', 'datetime', 'timestamp'], true)) {
                $fields[$name] = ['label' => $label, 'type' => 'date', 'column' => $name];
            } elseif ((str_ends_with($name, '_id') || str_ends_with($name, '_by')) && ($relation = $this->belongsTo($instance, $name)) !== null) {
                $fields[$name] = ['label' => $label, 'type' => 'relation', 'column' => $name, 'relation' => $relation[0], 'related' => $relation[1]];
            } elseif (in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double', 'numeric'], true)) {
                $fields[$name] = ['label' => $label, 'type' => 'number', 'column' => $name];
            } elseif (in_array($typeName, ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'string', 'enum'], true)) {
                $fields[$name] = ['label' => $label, 'type' => 'text', 'column' => $name];
            }
        }

        return $fields;
    }

    /**
     * The belongsTo relation behind a "…_id" column: [method, related model], or null.
     *
     * @return array{0: string, 1: class-string<Model>}|null
     */
    protected function belongsTo(Model $instance, string $column): ?array
    {
        $base = Str::replaceLast('_id', '', $column);
        // The usual names first, then every method declared to return a belongsTo (e.g. created_by → creator()).
        $declared = collect((new \ReflectionClass($instance))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn (\ReflectionMethod $method): bool => $method->getNumberOfRequiredParameters() === 0 && $method->getReturnType() instanceof \ReflectionNamedType && is_a($method->getReturnType()->getName(), BelongsTo::class, true))
            ->map(fn (\ReflectionMethod $method): string => $method->getName())
            ->all();

        foreach (array_unique([Str::camel($base), Str::camel(Str::after($base, '_')), ...$declared]) as $method) {
            if ($method === '' || ! method_exists($instance, $method)) {
                continue;
            }

            try {
                $relation = $instance->{$method}();
            } catch (Throwable) {
                continue;
            }

            if ($relation instanceof BelongsTo && $relation->getForeignKeyName() === $column) {
                return [$method, $relation->getRelated()::class];
            }
        }

        return null;
    }

    public static function titleOf(?Model $model): string
    {
        if ($model === null) {
            return '—';
        }

        foreach (['label', 'fullName', 'title'] as $method) {
            if (method_exists($model, $method)) {
                $value = rescue(fn () => $model->{$method}(), null, false);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        foreach (['name', 'title', 'reference', 'number', 'cow_code', 'plate_number', 'code', 'company_name', 'description'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_array($value) && filled($value[app()->getLocale()] ?? $value['nl'] ?? null)) {
                return (string) ($value[app()->getLocale()] ?? $value['nl']);
            }
        }

        return '#'.$model->getKey();
    }

    public static function enumLabel(?UnitEnum $case): string
    {
        if ($case === null) {
            return '';
        }

        return $case instanceof HasLabel ? (string) $case->getLabel() : Str::headline($case->name);
    }

    /**
     * The lists of a panel the user may open, as sources: key => source.
     *
     * @return array<string, self>
     */
    public static function all(string $panel): array
    {
        $resources = rescue(fn (): array => Filament::getPanel($panel)->getResources(), [], false);

        return collect($resources)
            ->map(fn (string $resource): self => new self($resource, $panel))
            ->filter(fn (self $source): bool => $source->canView())
            ->keyBy(fn (self $source): string => $source->key())
            ->all();
    }

    public static function find(string $panel, ?string $key): ?self
    {
        return $key === null ? null : (self::all($panel)[$key] ?? null);
    }

    /**
     * key => label, alphabetical.
     *
     * @return array<string, string>
     */
    public static function options(string $panel): array
    {
        return collect(self::all($panel))->map(fn (self $source): string => $source->label())->sort()->all();
    }
}
