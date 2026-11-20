<?php

namespace App\Filament\Admin\Resources\CertificateTypes;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\CertificateTypes\Pages\ManageCertificateTypes;
use App\Models\CertificateType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * "Attesttypes" under "Opleidingen & medisch onderzoek" (like VCA > Certificate types in Jacobs ERP): the
 * kinds of certificates kept per employee. A type gets a name per language, a short column header, whether
 * and how it shows in the overview, and its validity: the expiry date of its certificates follows from
 * the date obtained (no validity: they never expire, nobody enters an expiry date). A type in use is made inactive, not deleted.
 * Needs "certificates.types".
 */
class CertificateTypeResource extends Resource
{
    protected static ?string $model = CertificateType::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'certificate-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return modules()->employees() && (bool) auth()->user()?->hasPermission('certificates.types');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    /**
     * A type somebody has (or asked for) stays; it can be made inactive instead.
     */
    public static function canDelete(Model $record): bool
    {
        /** @var CertificateType $record */
        return static::canViewAny() && ! $record->isInUse();
    }

    public static function getModelLabel(): string
    {
        return __('erp.certificate_types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.certificate_types.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.certificate_types.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Grid::make(3)->columnSpanFull()->schema([
                    TextInput::make('name_nl')->label(__('erp.certificate_types.name_nl'))->requiredWithoutAll(['name_fr', 'name_en'])->maxLength(150),
                    TextInput::make('name_fr')->label(__('erp.certificate_types.name_fr'))->maxLength(150),
                    TextInput::make('name_en')->label(__('erp.certificate_types.name_en'))->maxLength(150),
                    TextInput::make('short_nl')->label(__('erp.certificate_types.short_nl'))->helperText(__('erp.certificate_types.short_help'))->maxLength(60),
                    TextInput::make('short_fr')->label(__('erp.certificate_types.short_fr'))->maxLength(60),
                    TextInput::make('short_en')->label(__('erp.certificate_types.short_en'))->maxLength(60),
                ]),
                ToggleButtons::make('overview')
                    ->label(__('erp.certificate_types.overview'))
                    ->options(collect(CertificateType::OVERVIEWS)->mapWithKeys(fn (string $overview): array => [$overview => __('erp.certificate_types.overviews.'.$overview)])->all())
                    ->default(CertificateType::OVERVIEW_HELD)
                    ->required()
                    ->columnSpanFull(),
                Select::make('kind')
                    ->label(__('erp.certificate_types.kind'))
                    ->options(collect(CertificateType::KINDS)->mapWithKeys(fn (string $kind): array => [$kind => __('erp.certificate_types.kinds.'.$kind)])->all())
                    ->placeholder(__('erp.certificate_types.kinds.standard'))
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('validity_months')
                    ->label(__('erp.certificate_types.validity'))
                    ->helperText(__('erp.certificate_types.validity_help'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1200)
                    ->suffix(__('erp.certificate_types.months_suffix'))
                    ->visible(fn (Get $get): bool => $get('kind') !== CertificateType::KIND_SCHOOL_DEGREE),
                Toggle::make('shows_validity')
                    ->label(__('erp.certificate_types.shows_validity'))
                    ->helperText(__('erp.certificate_types.shows_validity_help'))
                    ->default(true),
                Toggle::make('is_active')->label(__('erp.fields.active'))->helperText(__('erp.certificate_types.active_help'))->default(true),
                TextInput::make('sort')->label(__('erp.interventions.sort'))->integer()->minValue(0)->maxValue(65000)->default(fn (): int => (int) CertificateType::query()->max('sort') + 10)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->paginated(false)
            ->reorderable('sort')
            ->modifyQueryUsing(fn ($query) => $query->withCount('certificates'))
            ->columns([
                TextColumn::make('name_nl')->label(__('erp.fields.name'))->state(fn (CertificateType $record): string => $record->getLabel())->description(fn (CertificateType $record): ?string => $record->shortLabel() !== $record->getLabel() ? $record->shortLabel() : null)->searchable(['name_nl', 'name_fr', 'name_en', 'short_nl']),
                TextColumn::make('overview')->label(__('erp.certificate_types.overview'))->formatStateUsing(fn (string $state): string => __('erp.certificate_types.overviews.'.$state))->badge()->color(fn (string $state): string => match ($state) {
                    CertificateType::OVERVIEW_ALWAYS => 'success',
                    CertificateType::OVERVIEW_HELD => 'info',
                    default => 'gray',
                }),
                TextColumn::make('kind')->label(__('erp.certificate_types.kind'))->formatStateUsing(fn (?string $state): string => __('erp.certificate_types.kinds.'.($state ?: 'standard')))->placeholder(__('erp.certificate_types.kinds.standard'))->toggleable(),
                TextColumn::make('validity_months')->label(__('erp.certificate_types.validity'))->state(fn (CertificateType $record): string => $record->validityLabel())->color(fn (CertificateType $record): ?string => $record->canExpire() ? null : 'gray'),
                IconColumn::make('shows_validity')->label(__('erp.certificate_types.shows_validity'))->boolean()->toggleable(),
                TextColumn::make('certificates_count')->label(__('erp.employees.certificates'))->badge(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CertificateType $record): bool => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCertificateTypes::route('/'),
        ];
    }
}
