<?php

namespace App\Filament\Admin\Resources\Absences;

use App\Enums\AbsenceType;
use App\Enums\IncidentType;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Absences\Pages\ManageAbsences;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\Absence;
use App\Models\Incident;
use App\Models\ParentalLeave;
use App\Models\User;
use App\Support\ShortLeaveReasons;
use App\Support\WorkingDays;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Medical leave, overtime taken as paid leave, family leave, unpaid leave and justified absence
 * ("gewettigd afwezig", several loose periods at once with one document), entered by administrators only.
 * They show in everyone's vacation calendar and on the employee's "My absences" page.
 */
class AbsenceResource extends Resource
{
    protected static ?string $model = Absence::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'absences';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.resources.absence.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.absence.plural');
    }

    /**
     * Absences still waiting for a certificate.
     */
    public static function getNavigationBadge(): ?string
    {
        $missing = Absence::query()->whereNull('document')->whereIn('type', self::certificateTypes())->count();

        return $missing > 0 ? (string) $missing : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.absences.missing_certificates');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('user_id')
                    ->label(__('erp.absences.employee'))
                    ->options(fn (?Absence $record): array => User::query()
                        ->where(fn (Builder $query) => $query->where('is_active', true)->withTimesheet()->when($record, fn (Builder $query) => $query->orWhere('id', $record->user_id)))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),
                ToggleButtons::make('type')
                    ->label(__('erp.absences.type'))
                    ->options(AbsenceType::class)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get, string $operation): bool => ! self::severalPeriods($get('type'), $operation)),
                DatePicker::make('end_date')
                    ->label(__('erp.fields.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date')
                    ->default(fn (Get $get) => $get('start_date'))
                    ->required()
                    ->live()
                    ->visible(fn (Get $get, string $operation): bool => ! self::severalPeriods($get('type'), $operation)),
                Toggle::make('half_day')
                    ->label(__('erp.fields.half_day'))
                    ->visible(fn (Get $get, string $operation): bool => ! self::severalPeriods($get('type'), $operation) && filled($get('start_date')) && $get('start_date') === $get('end_date'))
                    ->live()
                    ->columnSpanFull(),
                Text::make(fn (Get $get): string => __('erp.absences.days_preview', ['days' => WorkingDays::count($get('start_date'), $get('end_date'), (bool) $get('half_day'))]))
                    ->visible(fn (Get $get, string $operation): bool => ! self::severalPeriods($get('type'), $operation))
                    ->columnSpanFull(),
                // Justified absences: several loose periods at once, each its own absence.
                Repeater::make('periods')
                    ->label(__('erp.absences.periods'))
                    ->helperText(__('erp.absences.periods_help'))
                    ->schema([
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
                            ->visible(fn (Get $get): bool => filled($get('start_date')) && $get('start_date') === $get('end_date'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->maxItems(50)
                    ->addActionLabel(__('erp.absences.add_period'))
                    ->itemLabel(fn (array $state): ?string => filled($state['start_date'] ?? null)
                        ? __('erp.absences.days_preview', ['days' => WorkingDays::count($state['start_date'], $state['end_date'] ?? $state['start_date'], (bool) ($state['half_day'] ?? false))])
                        : null)
                    ->visible(fn (Get $get, string $operation): bool => self::severalPeriods($get('type'), $operation))
                    ->columnSpanFull(),
                // Klein verlet: the reason of the royal decree, which sets the most days.
                Select::make('reason')
                    ->label(__('erp.short_leave.reason'))
                    ->options(ShortLeaveReasons::options())
                    ->searchable()
                    ->required(fn (Get $get): bool => self::type($get('type')) === AbsenceType::ShortLeave)
                    ->visible(fn (Get $get): bool => self::type($get('type')) === AbsenceType::ShortLeave)
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $max = ShortLeaveReasons::maxDays((string) $value);
                        $days = WorkingDays::count($get('start_date'), $get('end_date'), (bool) $get('half_day'));

                        if ($max !== null && $days > $max) {
                            $fail(__('erp.short_leave.too_many', ['max' => $max]));
                        }
                    })
                    ->columnSpanFull(),
                // Work accident: the accident report it follows from.
                Select::make('incident_id')
                    ->label(__('erp.absences.incident'))
                    ->helperText(__('erp.absences.incident_help'))
                    ->options(fn (?Absence $record): array => Incident::query()
                        ->where(fn (Builder $query) => $query->where('type', IncidentType::Accident)->when($record?->incident_id, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
                        ->latest('reported_at')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Incident $incident): array => [$incident->id => trim($incident->reported_at?->format('d/m/Y').' · '.$incident->reporter_name.' · '.str($incident->description)->limit(60), ' ·')])
                        ->all())
                    ->searchable()
                    ->required(fn (Get $get): bool => self::type($get('type')) === AbsenceType::WorkAccident)
                    ->visible(fn (Get $get): bool => self::type($get('type')) === AbsenceType::WorkAccident)
                    ->columnSpanFull(),
                // Parental leave: one of the parental leaves administration enabled for this employee.
                Select::make('parental_leave_id')
                    ->label(__('erp.parental_leave.singular'))
                    ->helperText(__('erp.parental_leave.absence_help'))
                    ->options(fn (Get $get): array => ParentalLeave::query()
                        ->whereHas('employee', fn (Builder $query) => $query->where('user_id', (int) $get('user_id')))
                        ->orderByDesc('starts_on')
                        ->get()
                        ->mapWithKeys(fn (ParentalLeave $leave): array => [$leave->id => $leave->label()])
                        ->all())
                    ->required(fn (Get $get): bool => self::type($get('type')) === AbsenceType::Parental)
                    ->visible(fn (Get $get): bool => self::type($get('type')) === AbsenceType::Parental)
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $leave = ParentalLeave::query()->find($value);

                        if ($leave && filled($get('start_date')) && ! $leave->covers((string) $get('start_date'), (string) ($get('end_date') ?: $get('start_date')))) {
                            $fail(__('erp.parental_leave.errors.outside', ['period' => $leave->starts_on->format('d/m/Y').' – '.$leave->ends_on->format('d/m/Y')]));
                        }
                    })
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label(__('erp.fields.notes'))
                    ->helperText(__('erp.absences.note_help'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                FileUpload::make('document')
                    ->label(fn (Get $get): string => self::isJustified($get('type')) ? __('erp.absences.document') : __('erp.absences.certificate'))
                    ->helperText(fn (Get $get): string => match (self::type($get('type'))) {
                        AbsenceType::Justified => __('erp.absences.justified_document_help'),
                        AbsenceType::WorkAccident => __('erp.absences.work_accident_document_help'),
                        default => __('erp.absences.certificate_admin_help'),
                    })
                    ->disk(Absence::DISK)
                    ->directory(fn (Get $get): string => Absence::directoryFor((int) $get('user_id')))
                    ->visibility('private')
                    ->acceptedFileTypes(Absence::DOCUMENT_TYPES)
                    ->maxSize(Absence::DOCUMENT_MAX_KILOBYTES)
                    ->storeFileNamesIn('document_name')
                    // A certificate for sickness, the proof for a justified absence.
                    ->visible(fn (Get $get): bool => self::takesDocument($get('type')))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Marks a certificate changed in the admin form as the administrator's, so the employee can
     * only download it from then on.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withDocumentSource(array $data, ?Absence $record = null): array
    {
        $document = $data['document'] ?? null;

        if (filled($document) && $document !== $record?->document) {
            $data['document_source'] = Absence::SOURCE_ADMIN;
            $data['document_uploaded_at'] = now();
        }

        return $data;
    }

    public static function needsCertificate(mixed $type): bool
    {
        return (bool) self::type($type)?->needsCertificate();
    }

    public static function takesDocument(mixed $type): bool
    {
        return (bool) self::type($type)?->takesDocument();
    }

    public static function isJustified(mixed $type): bool
    {
        return self::type($type) === AbsenceType::Justified;
    }

    /**
     * New justified absences take several periods; once saved, each is edited on its own.
     */
    public static function severalPeriods(mixed $type, string $operation): bool
    {
        return $operation === 'create' && (bool) self::type($type)?->allowsSeveralPeriods();
    }

    /**
     * @return list<string>
     */
    public static function certificateTypes(): array
    {
        return array_values(array_map(fn (AbsenceType $type): string => $type->value, array_filter(AbsenceType::cases(), fn (AbsenceType $type): bool => $type->needsCertificate())));
    }

    public static function type(mixed $type): ?AbsenceType
    {
        return $type instanceof AbsenceType ? $type : AbsenceType::tryFrom((string) $type);
    }

    /**
     * Creates the absence, or one per period for justified absences: each gets its own copy of the
     * document, so removing one period never takes the file of another.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createAbsences(array $data): Absence
    {
        $periods = self::severalPeriods($data['type'] ?? null, 'create') ? array_values((array) ($data['periods'] ?? [])) : [];
        unset($data['periods']);

        if ($periods === []) {
            return Absence::query()->create($data);
        }

        return DB::transaction(function () use ($data, $periods): Absence {
            $created = [];

            foreach ($periods as $index => $period) {
                $document = $data['document'] ?? null;

                if ($index > 0 && filled($document)) {
                    $copy = dirname($document).'/'.pathinfo($document, PATHINFO_FILENAME).'-'.($index + 1).'.'.pathinfo($document, PATHINFO_EXTENSION);
                    Storage::disk(Absence::DISK)->copy($document, $copy);
                    $document = $copy;
                }

                $created[] = Absence::query()->create([
                    ...$data,
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'] ?? $period['start_date'],
                    'half_day' => ($period['start_date'] === ($period['end_date'] ?? $period['start_date'])) && (bool) ($period['half_day'] ?? false),
                    'document' => $document,
                ]);
            }

            return $created[0];
        });
    }

    public static function documentUrl(Absence $absence): string
    {
        return route('absences.document', ['absence' => $absence]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('erp.absences.employee'))
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('erp.absences.type'))
                    ->badge(),
                TextColumn::make('start_date')
                    ->label(__('erp.fields.period'))
                    ->formatStateUsing(fn (Absence $record): string => VacationRequestResource::period($record))
                    ->description(fn (Absence $record): ?string => $record->half_day ? __('erp.fields.half_day') : null)
                    ->sortable(),
                TextColumn::make('days')
                    ->label(__('erp.vacations.days'))
                    ->numeric(decimalPlaces: 1),
                TextColumn::make('document_source')
                    ->label(__('erp.absences.certificate'))
                    ->state(fn (Absence $record): string => match (true) {
                        ! $record->hasDocument() && ! $record->type->needsCertificate() => __('erp.absences.not_needed'),
                        $record->type === AbsenceType::WorkAccident && ! $record->isPaidWorkAccident() => __('erp.absences.missing_unpaid'),
                        ! $record->hasDocument() => __('erp.absences.missing'),
                        $record->document_source === Absence::SOURCE_EMPLOYEE => __('erp.absences.by_employee'),
                        default => __('erp.absences.by_admin'),
                    })
                    ->badge()
                    ->color(fn (Absence $record): string => match (true) {
                        $record->hasDocument() => 'success',
                        $record->type->needsCertificate() => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (Absence $record): ?Heroicon => match (true) {
                        $record->hasDocument() => Heroicon::OutlinedDocumentCheck,
                        $record->type->needsCertificate() => Heroicon::OutlinedExclamationCircle,
                        default => null,
                    })
                    ->url(fn (Absence $record): ?string => $record->hasDocument() ? self::documentUrl($record) : null, shouldOpenInNewTab: true),
                TextColumn::make('note')
                    ->label(__('erp.fields.notes'))
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('erp.absences.employee'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y') + 1, (int) date('Y') - 5))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereYear('start_date', $year))),
                SelectFilter::make('document')
                    ->label(__('erp.absences.certificate'))
                    ->options(['missing' => __('erp.absences.missing'), 'present' => __('erp.absences.present')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'missing' => $query->whereNull('document')->whereIn('type', self::certificateTypes()),
                        'present' => $query->whereNotNull('document'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('certificate')
                    ->label(__('erp.absences.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Absence $record): bool => $record->hasDocument())
                    ->url(fn (Absence $record): string => self::documentUrl($record), shouldOpenInNewTab: true),
                EditAction::make()
                    ->mutateDataUsing(fn (array $data, Absence $record): array => self::withDocumentSource($data, $record)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAbsences::route('/'),
        ];
    }
}
