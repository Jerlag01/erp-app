<?php

namespace App\Filament\Resources\AccessBadges;

use App\Enums\NavigationGroup;
use App\Filament\Resources\AccessBadges\Pages\ManageAccessBadges;
use App\Models\AccessBadge;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Badges (7 digits) and electronic keys (4 digits), one entry per employee, with CLAD permission and
 * clearance. Employees who left stay in the list as deactivated.
 */
class AccessBadgeResource extends Resource
{
    protected static ?string $model = AccessBadge::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'access-badges';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('erp.access.badge');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.access.badges');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                AccessEmployees::select('access_badges'),
                TextInput::make('badge_number')
                    ->label(__('erp.access.badge_number'))
                    ->helperText(__('erp.access.badge_number_help'))
                    ->regex(AccessBadge::BADGE_PATTERN)
                    ->length(7)
                    ->unique(ignoreRecord: true)
                    ->requiredWithout('key_number')
                    ->validationMessages(['unique' => __('erp.access.number_taken'), 'required_without' => __('erp.access.badge_or_key')])
                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                TextInput::make('key_number')
                    ->label(__('erp.access.key_number'))
                    ->helperText(__('erp.access.key_number_help'))
                    ->regex(AccessBadge::KEY_PATTERN)
                    ->length(4)
                    ->unique(ignoreRecord: true)
                    ->requiredWithout('badge_number')
                    ->validationMessages(['unique' => __('erp.access.number_taken'), 'required_without' => __('erp.access.badge_or_key')])
                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                Toggle::make('has_clad')->label(__('erp.access.has_clad'))->columnSpanFull(),
                Textarea::make('clearance')->label(__('erp.access.clearance'))->helperText(__('erp.access.clearance_help'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->defaultSort('badge_number')
            ->columns([
                AccessEmployees::employeeColumn(),
                AccessEmployees::statusColumn(),
                TextColumn::make('badge_number')->label(__('erp.access.badge_number'))->fontFamily(FontFamily::Mono)->searchable()->sortable()->placeholder('—'),
                TextColumn::make('key_number')->label(__('erp.access.key_number'))->fontFamily(FontFamily::Mono)->searchable()->sortable()->placeholder('—'),
                IconColumn::make('has_clad')->label(__('erp.access.has_clad'))->boolean(),
                TextColumn::make('clearance')->label(__('erp.access.clearance'))->wrap()->limit(80)->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccessBadges::route('/'),
        ];
    }
}
