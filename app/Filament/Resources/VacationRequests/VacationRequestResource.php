<?php

namespace App\Filament\Resources\VacationRequests;

use App\Enums\NavigationGroup;
use App\Enums\VacationStatus;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\VacationRequests\Pages\ManageVacationRequests;
use App\Models\Absence;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\WorkingDays;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Vacation requests of the team (all of them for administrators), to approve or reject.
 */
class VacationRequestResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = VacationRequest::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'vacation-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.resources.vacation_request.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.vacation_request.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->hasPermission('vacations.approve')) {
            return null;
        }

        $pending = static::getEloquentQuery()->where('status', VacationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * Administrators can enter or correct a request for someone; it is then approved straight away.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('user_id')
                    ->label(__('erp.fields.employee'))
                    ->relationship('user', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live(),
                DatePicker::make('end_date')
                    ->label(__('erp.fields.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date')
                    ->required()
                    ->live(),
                Toggle::make('half_day')
                    ->label(__('erp.fields.half_day'))
                    ->helperText(fn (Get $get): string => __('erp.vacations.counted', ['days' => WorkingDays::count($get('start_date'), $get('end_date'), (bool) $get('half_day'))]))
                    ->live(),
                Select::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(VacationStatus::class)
                    ->default(VacationStatus::Approved)
                    ->required(),
                Textarea::make('reason')
                    ->label(__('erp.fields.reason'))
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('review_note')
                    ->label(__('erp.fields.review_note'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user.teams', 'reviewer']))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw("status = 'pending' desc")->orderByDesc('start_date'))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('erp.fields.employee'))
                    ->description(fn (VacationRequest $record): ?string => modules()->teams() ? $record->user?->teams->pluck('name')->implode(', ') ?: null : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('erp.fields.period'))
                    ->formatStateUsing(fn (VacationRequest $record): string => static::period($record))
                    ->description(fn (VacationRequest $record): ?string => $record->half_day ? __('erp.fields.half_day') : null)
                    ->sortable(),
                TextColumn::make('days')
                    ->label(__('erp.vacations.days'))
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('erp.fields.reason'))
                    ->limit(40)
                    ->tooltip(fn (VacationRequest $record): ?string => $record->reason)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reviewer.name')
                    ->label(__('erp.fields.reviewed_by'))
                    ->description(fn (VacationRequest $record): ?string => $record->review_note)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('erp.fields.requested_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(VacationStatus::class)
                    ->default(VacationStatus::Pending->value),
                SelectFilter::make('user')
                    ->label(__('erp.fields.employee'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y') + 1, (int) date('Y') - 3))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $year) => $query->whereYear('start_date', $year))),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('erp.vacations.approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (VacationRequest $record): bool => Gate::allows('approve', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (VacationRequest $record): string => static::summary($record))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('erp.fields.review_note'))
                            ->rows(2),
                    ])
                    ->action(function (VacationRequest $record, array $data): void {
                        $record->approve(auth()->user(), $data['review_note'] ?? null);
                        static::notifyRequester($record);
                    })
                    ->successNotificationTitle(__('erp.vacations.approved')),
                Action::make('reject')
                    ->label(__('erp.vacations.reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (VacationRequest $record): bool => Gate::allows('approve', $record))
                    ->modalDescription(fn (VacationRequest $record): string => static::summary($record))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('erp.fields.review_note'))
                            ->helperText(__('erp.vacations.reject_help'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (VacationRequest $record, array $data): void {
                        $record->reject(auth()->user(), $data['review_note']);
                        static::notifyRequester($record);
                    })
                    ->successNotificationTitle(__('erp.vacations.rejected')),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function period(VacationRequest|Absence $record): string
    {
        return $record->start_date->isSameDay($record->end_date)
            ? $record->start_date->translatedFormat('D d/m/Y')
            : $record->start_date->format('d/m/Y').' → '.$record->end_date->format('d/m/Y');
    }

    protected static function summary(VacationRequest $record): string
    {
        $balance = $record->user->vacationBalance($record->start_date->year, ignoreRequestId: $record->id);

        return __('erp.vacations.summary', [
            'name' => $record->user->name,
            'period' => static::period($record),
            'days' => $record->days,
            'remaining' => $balance['remaining'],
        ]);
    }

    protected static function notifyRequester(VacationRequest $record): void
    {
        /** @var User $user */
        $user = $record->user;
        $locale = $user->preferredLocale();
        $approved = $record->status === VacationStatus::Approved;

        Notification::make()
            ->title(__($approved ? 'erp.vacations.notifications.approved_title' : 'erp.vacations.notifications.rejected_title', [], $locale))
            ->body(trim(static::period($record).'. '.$record->review_note))
            ->icon($approved ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->status($approved ? 'success' : 'danger')
            ->sendToDatabase($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVacationRequests::route('/'),
        ];
    }
}
