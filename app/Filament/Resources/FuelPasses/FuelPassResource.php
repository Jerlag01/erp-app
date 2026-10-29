<?php

namespace App\Filament\Resources\FuelPasses;

use App\Enums\NavigationGroup;
use App\Filament\Resources\FuelPasses\Pages\ManageFuelPasses;
use App\Models\FuelPass;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use UnitEnum;

/**
 * "Tankkaarten": the fuel passes of the fleet, one vehicle each. The PIN is never in the list: the
 * members of the vehicle's team and those with "fuel_passes.view_pin" open it with "Show PIN" (logged).
 */
class FuelPassResource extends Resource
{
    protected static ?string $model = FuelPass::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'fuel-passes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Fleet;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.fuel_passes.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.fuel_passes.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('vehicle_id')
                    ->label(__('erp.fuel_passes.vehicle'))
                    ->relationship('vehicle', 'plate_number', fn (Builder $query) => $query->visibleTo(auth()->user())->orderBy('plate_number'))
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $record): string => trim($record->plate_number.' · '.trim($record->brand.' '.$record->type).($record->team ? ' · '.$record->team->name : ''), ' ·'))
                    ->searchable(['plate_number', 'brand', 'type'])
                    ->preload()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('card_number')
                    ->label(__('erp.fuel_passes.card_number'))
                    ->helperText(__('erp.fuel_passes.card_number_help'))
                    ->required()
                    ->dehydrateStateUsing(fn (?string $state): string => preg_replace('/\D/', '', (string) $state))
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! preg_match(FuelPass::CARD_PATTERN, preg_replace('/\D/', '', (string) $value))) {
                            $fail(__('erp.fuel_passes.card_number_invalid'));
                        }
                    })
                    ->unique(ignoreRecord: true)
                    ->maxLength(30)
                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                // Changed only by typing a new one; never shown in the form.
                TextInput::make('pin_code')
                    ->label(__('erp.fuel_passes.pin_code'))
                    ->helperText(fn (?FuelPass $record): string => $record ? __('erp.fuel_passes.pin_keep') : __('erp.fuel_passes.pin_help'))
                    ->password()
                    ->revealable(false)
                    ->regex(FuelPass::PIN_PATTERN)
                    ->length(4)
                    ->required(fn (?FuelPass $record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->formatStateUsing(fn (): ?string => null)
                    ->extraInputAttributes(['inputmode' => 'numeric', 'autocomplete' => 'off']),
                // Month and year only, as on the card.
                TextInput::make('expires_on')
                    ->label(__('erp.fuel_passes.expires_on'))
                    ->placeholder(__('erp.fuel_passes.expires_on_placeholder'))
                    ->mask('99/9999')
                    ->regex(FuelPass::EXPIRY_PATTERN)
                    ->validationMessages(['regex' => __('erp.fuel_passes.expires_on_invalid')])
                    ->required()
                    ->formatStateUsing(fn (?FuelPass $record): ?string => $record?->expiryLabel())
                    ->dehydrateStateUsing(fn (?string $state): ?string => FuelPass::parseExpiry($state)),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle.team']))
            ->defaultSort('card_number')
            ->columns([
                TextColumn::make('card_number')
                    ->label(__('erp.fuel_passes.card_number'))
                    ->formatStateUsing(fn (FuelPass $record): string => $record->formattedCardNumber())
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyableState(fn (FuelPass $record): string => (string) $record->card_number)
                    ->searchable(),
                TextColumn::make('vehicle.plate_number')
                    ->label(__('erp.fuel_passes.vehicle'))
                    ->description(fn (FuelPass $record): string => trim($record->vehicle?->brand.' '.$record->vehicle?->type))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.team.name')
                    ->label(__('erp.resources.team.singular'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->teams()),
                TextColumn::make('expires_on')
                    ->label(__('erp.fuel_passes.expires_on'))
                    ->formatStateUsing(fn (FuelPass $record): ?string => $record->expiryLabel())
                    ->badge()
                    ->color(fn (FuelPass $record): string => $record->isExpired() ? 'danger' : 'success')
                    ->description(fn (FuelPass $record): ?string => $record->isExpired() ? __('erp.fuel_passes.expired') : null)
                    ->sortable(),
                TextColumn::make('pin')
                    ->label(__('erp.fuel_passes.pin_code'))
                    ->state('••••')
                    ->fontFamily(FontFamily::Mono)
                    ->color('gray'),
                TextColumn::make('notes')->label(__('erp.fields.notes'))->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label(__('erp.fuel_passes.vehicle'))
                    ->relationship('vehicle', 'plate_number', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('showPin')
                    ->label(__('erp.fuel_passes.show_pin'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (FuelPass $record): bool => $record->pinVisibleTo(auth()->user()))
                    ->authorize(fn (FuelPass $record): bool => $record->pinVisibleTo(auth()->user()))
                    ->modalHeading(fn (FuelPass $record): string => __('erp.fuel_passes.pin_heading', ['card' => $record->formattedCardNumber()]))
                    ->modalDescription(fn (FuelPass $record): string => $record->vehicle?->plate_number ?? '')
                    ->schema(function (FuelPass $record): array {
                        Log::info('Fuel pass PIN shown', ['fuel_pass' => $record->id, 'by' => auth()->id()]);

                        return [
                            Text::make((string) $record->pin_code)->size('lg')->weight(FontWeight::Bold)->fontFamily(FontFamily::Mono),
                        ];
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('erp.mobile.close')),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFuelPasses::route('/'),
        ];
    }
}
