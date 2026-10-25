<?php

namespace App\Filament\Resources\SecurityTags;

use App\Enums\NavigationGroup;
use App\Filament\Resources\AccessBadges\AccessEmployees;
use App\Filament\Resources\SecurityTags\Pages\ManageSecurityTags;
use App\Models\SecurityTag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The security system tags (2 digits), one per employee: the name in the system, access to the
 * warehouse and the office, and whether door keys were handed over.
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
                AccessEmployees::select('security_tags'),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->defaultSort('tag_number')
            ->columns([
                TextColumn::make('tag_number')->label(__('erp.access.tag_number'))->fontFamily(FontFamily::Mono)->searchable()->sortable(),
                AccessEmployees::employeeColumn(),
                TextColumn::make('system_name')->label(__('erp.access.system_name'))->searchable()->placeholder('—'),
                AccessEmployees::statusColumn(),
                IconColumn::make('access_warehouse')->label(__('erp.access.access_warehouse'))->boolean(),
                IconColumn::make('access_office')->label(__('erp.access.access_office'))->boolean(),
                IconColumn::make('door_keys')->label(__('erp.access.door_keys'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
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
