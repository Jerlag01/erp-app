<?php

namespace App\Filament\App\Resources\MyAbsences;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Absences\AbsenceResource;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\Absence;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Self-service: the employee's own medical leave, overtime taken as leave and family leave
 * (entered by an administrator). A missing certificate can be uploaded here; one uploaded by
 * the administrator can only be downloaded.
 */
class MyAbsenceResource extends Resource
{
    protected static ?string $model = Absence::class;

    protected static ?string $slug = 'my-absences';

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('erp.my.absences');
    }

    public static function getModelLabel(): string
    {
        return __('erp.resources.absence.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.my.absences');
    }

    /**
     * Not the policy (that is for administrators): everyone sees their own absences.
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return modules()->vacations() && auth()->check() ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading(__('erp.absences.none'))
            ->emptyStateIcon(Heroicon::OutlinedHeart)
            ->columns([
                TextColumn::make('start_date')
                    ->label(__('erp.fields.period'))
                    ->formatStateUsing(fn (Absence $record): string => VacationRequestResource::period($record))
                    ->description(fn (Absence $record): ?string => $record->half_day ? __('erp.fields.half_day') : null)
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('erp.absences.type'))
                    ->badge(),
                TextColumn::make('days')
                    ->label(__('erp.vacations.days'))
                    ->numeric(decimalPlaces: 1)
                    ->summarize(Sum::make()->label(__('erp.absences.total_days'))->numeric(decimalPlaces: 1)),
                TextColumn::make('document_source')
                    ->label(__('erp.absences.certificate'))
                    ->state(fn (Absence $record): string => match (true) {
                        ! $record->hasDocument() => __('erp.absences.missing'),
                        $record->document_source === Absence::SOURCE_EMPLOYEE => __('erp.absences.uploaded_by_you'),
                        default => __('erp.absences.by_admin'),
                    })
                    ->badge()
                    ->color(fn (Absence $record): string => $record->hasDocument() ? 'success' : 'warning'),
                TextColumn::make('note')
                    ->label(__('erp.fields.notes'))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('erp.absences.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Absence $record): bool => $record->hasDocument())
                    ->url(fn (Absence $record): string => AbsenceResource::documentUrl($record), shouldOpenInNewTab: true),
                Action::make('upload')
                    ->label(fn (Absence $record): string => $record->hasDocument() ? __('erp.absences.replace') : __('erp.absences.upload'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color(fn (Absence $record): string => $record->hasDocument() ? 'gray' : 'primary')
                    ->visible(fn (Absence $record): bool => Gate::allows('uploadDocument', $record))
                    ->authorize(fn (Absence $record): bool => Gate::allows('uploadDocument', $record))
                    ->modalHeading(__('erp.absences.upload'))
                    ->modalDescription(__('erp.absences.upload_help'))
                    ->schema([
                        FileUpload::make('document')
                            ->hiddenLabel()
                            ->disk(Absence::DISK)
                            ->directory(fn (Absence $record): string => Absence::directoryFor($record->user_id))
                            ->visibility('private')
                            ->acceptedFileTypes(Absence::DOCUMENT_TYPES)
                            ->maxSize(Absence::DOCUMENT_MAX_KILOBYTES)
                            ->storeFileNamesIn('document_name')
                            ->required(),
                    ])
                    ->action(function (Absence $record, array $data): void {
                        $path = is_array($data['document']) ? reset($data['document']) : $data['document'];
                        $name = $data['document_name'] ?? null;
                        $record->attachDocument($path, is_array($name) ? ($name[$path] ?? reset($name)) : $name, Absence::SOURCE_EMPLOYEE);
                        self::notifyAdministrators($record);
                    })
                    ->successNotificationTitle(__('erp.absences.uploaded')),
            ]);
    }

    /**
     * Lets the administrators know a certificate came in.
     */
    protected static function notifyAdministrators(Absence $absence): void
    {
        $admins = User::query()->where('is_active', true)->whereHas('role', fn (Builder $query) => $query->where('is_admin', true))->get();

        foreach ($admins as $admin) {
            $locale = $admin->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__('erp.absences.notification_title', ['name' => $absence->user->name], $locale))
                ->body(__('erp.enums.absence_type.'.$absence->type->value, [], $locale).' · '.VacationRequestResource::period($absence))
                ->icon(Heroicon::OutlinedDocumentArrowUp)
                ->actions([
                    Action::make('open')
                        ->label(__('erp.absences.download', [], $locale))
                        ->url(AbsenceResource::documentUrl($absence), shouldOpenInNewTab: true),
                ])
                ->sendToDatabase($admin);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyAbsences::route('/'),
        ];
    }
}
