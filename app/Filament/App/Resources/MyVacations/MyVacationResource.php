<?php

namespace App\Filament\App\Resources\MyVacations;

use App\Enums\NavigationGroup;
use App\Enums\VacationStatus;
use App\Filament\App\Resources\MyVacations\Pages\ListMyVacations;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\VacationRequest;
use App\Support\WorkingDays;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Self-service: every employee, guests included, can request vacation and follow it up.
 */
class MyVacationResource extends Resource
{
    protected static ?string $model = VacationRequest::class;

    protected static ?string $slug = 'my-vacation';

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('erp.my.vacation');
    }

    public static function getModelLabel(): string
    {
        return __('erp.resources.vacation_request.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.my.vacation');
    }

    /**
     * Not the policy (that covers approving others): anyone may manage their own requests.
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return modules()->vacations() && auth()->check() ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::getViewAnyAuthorizationResponse();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return $record->user_id === auth()->id() ? static::getViewAnyAuthorizationResponse() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(today()->startOfYear())
                    ->required()
                    ->live(),
                DatePicker::make('end_date')
                    ->label(__('erp.fields.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date')
                    ->default(fn (Get $get) => $get('start_date'))
                    ->required()
                    ->live(),
                Toggle::make('half_day')
                    ->label(__('erp.fields.half_day'))
                    ->helperText(__('erp.vacations.half_day_help'))
                    ->visible(fn (Get $get): bool => filled($get('start_date')) && $get('start_date') === $get('end_date'))
                    ->live()
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label(__('erp.fields.reason'))
                    ->placeholder(__('erp.vacations.reason_placeholder'))
                    ->rows(2)
                    ->columnSpanFull(),
                Text::make(function (Get $get): string {
                    $days = WorkingDays::count($get('start_date'), $get('end_date'), (bool) $get('half_day'));
                    $year = $get('start_date') ? (int) substr((string) $get('start_date'), 0, 4) : (int) date('Y');

                    return __('erp.vacations.preview', [
                        'days' => $days,
                        'remaining' => auth()->user()->vacationBalance($year)['remaining'],
                        'year' => $year,
                    ]);
                })->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('reviewer'))
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading(__('erp.vacations.none_yet'))
            ->emptyStateIcon('heroicon-o-sun')
            ->columns([
                TextColumn::make('start_date')
                    ->label(__('erp.fields.period'))
                    ->formatStateUsing(fn (VacationRequest $record): string => VacationRequestResource::period($record))
                    ->description(fn (VacationRequest $record): ?string => $record->half_day ? __('erp.fields.half_day') : null)
                    ->sortable(),
                TextColumn::make('days')
                    ->label(__('erp.vacations.days'))
                    ->numeric(decimalPlaces: 1),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge(),
                TextColumn::make('reason')
                    ->label(__('erp.fields.reason'))
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('reviewer.name')
                    ->label(__('erp.fields.reviewed_by'))
                    ->description(fn (VacationRequest $record): ?string => $record->review_note)
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('erp.vacations.cancel'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (VacationRequest $record): bool => $record->canBeCancelledBy(auth()->user()))
                    ->requiresConfirmation()
                    ->action(fn (VacationRequest $record) => $record->update(['status' => VacationStatus::Cancelled]))
                    ->successNotificationTitle(__('erp.vacations.cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyVacations::route('/'),
        ];
    }
}
