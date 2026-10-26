<?php

namespace App\Filament\Resources\SecurityTags;

use App\Enums\NavigationGroup;
use App\Filament\Resources\AccessBadges\AccessEmployees;
use App\Filament\Resources\SecurityTags\Pages\ManageSecurityTags;
use App\Models\SecurityTag;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * The security system tags (2 digits): the name in the system, access to the warehouse and the office,
 * and whether door keys were handed over. A tag belongs to one employee, to one user with a login who
 * is not in the employee register, or to nobody; a lost tag is marked lost.
 */
class SecurityTagResource extends Resource
{
    protected static ?string $model = SecurityTag::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'security-tags';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 61;

    public static function getModelLabel(): string
    {
        return __('erp.access.tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.access.tags');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('holder')
                    ->label(__('erp.access.holder'))
                    ->options([
                        SecurityTag::HOLDER_EMPLOYEE => __('erp.access.holders.employee'),
                        SecurityTag::HOLDER_USER => __('erp.access.holders.user'),
                        SecurityTag::HOLDER_NONE => __('erp.access.holders.none'),
                    ])
                    ->default(SecurityTag::HOLDER_EMPLOYEE)
                    ->afterStateHydrated(fn (ToggleButtons $component, ?SecurityTag $record) => $record ? $component->state($record->holderType()) : null)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                AccessEmployees::select('security_tags')
                    ->required(fn (Get $get): bool => $get('holder') === SecurityTag::HOLDER_EMPLOYEE)
                    ->visible(fn (Get $get): bool => $get('holder') === SecurityTag::HOLDER_EMPLOYEE),
                // Users with a login who are not in the employee register (e.g. cleaners, externals).
                Select::make('user_id')
                    ->label(__('erp.access.user'))
                    ->helperText(__('erp.access.user_help'))
                    ->options(fn (?SecurityTag $record): array => User::query()
                        ->whereDoesntHave('employee')
                        ->where(fn (Builder $query) => $query->where('is_active', true)->when($record?->user_id, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(fn (Get $get): bool => $get('holder') === SecurityTag::HOLDER_USER)
                    ->rule(fn (?SecurityTag $record) => Rule::unique('security_tags', 'user_id')->ignore($record?->id))
                    ->validationMessages(['unique' => __('erp.access.one_per_user')])
                    ->visible(fn (Get $get): bool => $get('holder') === SecurityTag::HOLDER_USER)
                    ->columnSpanFull(),
                TextInput::make('tag_number')
                    ->label(__('erp.access.tag_number'))
                    ->helperText(__('erp.access.tag_number_help'))
                    ->required()
                    ->regex(SecurityTag::TAG_PATTERN)
                    ->length(2)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => __('erp.access.number_taken')])
                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                TextInput::make('system_name')->label(__('erp.access.system_name'))->maxLength(100),
                Grid::make(3)->columnSpanFull()->schema([
                    Checkbox::make('access_warehouse')->label(__('erp.access.access_warehouse')),
                    Checkbox::make('access_office')->label(__('erp.access.access_office')),
                    Checkbox::make('door_keys')->label(__('erp.access.door_keys')),
                ]),
                Toggle::make('is_lost')
                    ->label(__('erp.access.lost'))
                    ->helperText(fn (?SecurityTag $record): string => $record?->lost_at
                        ? __('erp.access.lost_since', ['date' => $record->lost_at->format('d/m/Y H:i')])
                        : __('erp.access.lost_help'))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Only the chosen holder is kept: the other (or both, for an unassigned tag) is cleared.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function holderData(array $data): array
    {
        $holder = $data['holder'] ?? SecurityTag::HOLDER_EMPLOYEE;
        unset($data['holder']);

        return [
            ...$data,
            'employee_id' => $holder === SecurityTag::HOLDER_EMPLOYEE ? ($data['employee_id'] ?? null) : null,
            'user_id' => $holder === SecurityTag::HOLDER_USER ? ($data['user_id'] ?? null) : null,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'user']))
            ->defaultSort('tag_number')
            ->columns([
                TextColumn::make('tag_number')->label(__('erp.access.tag_number'))->fontFamily(FontFamily::Mono)->searchable()->sortable(),
                TextColumn::make('holder')
                    ->label(__('erp.access.holder'))
                    ->state(fn (SecurityTag $record): ?string => $record->holderName())
                    ->description(fn (SecurityTag $record): ?string => $record->holderType() === SecurityTag::HOLDER_USER ? __('erp.access.holders.user') : null)
                    ->placeholder(__('erp.access.holders.none'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->whereHas('employee', fn (Builder $query) => $query->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))),
                TextColumn::make('system_name')->label(__('erp.access.system_name'))->searchable()->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('erp.access.status'))
                    ->state(fn (SecurityTag $record): string => __('erp.access.tag_statuses.'.$record->status()))
                    ->badge()
                    ->color(fn (SecurityTag $record): string => match ($record->status()) {
                        SecurityTag::STATUS_ACTIVE => 'success',
                        SecurityTag::STATUS_LOST => 'danger',
                        SecurityTag::STATUS_UNASSIGNED => 'info',
                        default => 'gray',
                    })
                    ->description(fn (SecurityTag $record): ?string => $record->is_lost && $record->lost_at ? $record->lost_at->format('d/m/Y') : null),
                IconColumn::make('access_warehouse')->label(__('erp.access.access_warehouse'))->boolean(),
                IconColumn::make('access_office')->label(__('erp.access.access_office'))->boolean(),
                IconColumn::make('door_keys')->label(__('erp.access.door_keys'))->boolean(),
                ToggleColumn::make('is_lost')
                    ->label(__('erp.access.lost'))
                    ->onColor('danger')
                    ->disabled(fn (SecurityTag $record): bool => ! Gate::allows('update', $record)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data): array => self::holderData($data)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSecurityTags::route('/'),
        ];
    }
}
