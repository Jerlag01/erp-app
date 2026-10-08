<?php

namespace App\Filament\Admin\Resources\Absences;

use App\Enums\AbsenceType;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Absences\Pages\ManageAbsences;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\Absence;
use App\Models\User;
use App\Support\WorkingDays;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use UnitEnum;

/**
 * Medical leave, overtime taken as paid leave and family leave, entered by administrators only.
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
        $missing = Absence::query()->whereNull('document')->where('type', AbsenceType::Medical)->count();

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
                        ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhere('id', $record->user_id)))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),
                ToggleButtons::make('type')
                    ->label(__('erp.absences.type'))
                    ->options(AbsenceType::class)
                    ->inline()
                    ->required()
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
                    ->default(fn (Get $get) => $get('start_date'))
                    ->required()
                    ->live(),
                Toggle::make('half_day')
                    ->label(__('erp.fields.half_day'))
                    ->visible(fn (Get $get): bool => filled($get('start_date')) && $get('start_date') === $get('end_date'))
                    ->live()
                    ->columnSpanFull(),
                Text::make(fn (Get $get): string => __('erp.absences.days_preview', ['days' => WorkingDays::count($get('start_date'), $get('end_date'), (bool) $get('half_day'))]))
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label(__('erp.fields.notes'))
                    ->helperText(__('erp.absences.note_help'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                FileUpload::make('document')
                    ->label(__('erp.absences.certificate'))
                    ->helperText(__('erp.absences.certificate_admin_help'))
                    ->disk(Absence::DISK)
                    ->directory(fn (Get $get): string => Absence::directoryFor((int) $get('user_id')))
                    ->visibility('private')
                    ->acceptedFileTypes(Absence::DOCUMENT_TYPES)
                    ->maxSize(Absence::DOCUMENT_MAX_KILOBYTES)
                    ->storeFileNamesIn('document_name')
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
                        ! $record->hasDocument() => __('erp.absences.missing'),
                        $record->document_source === Absence::SOURCE_EMPLOYEE => __('erp.absences.by_employee'),
                        default => __('erp.absences.by_admin'),
                    })
                    ->badge()
                    ->color(fn (Absence $record): string => $record->hasDocument() ? 'success' : 'warning')
                    ->icon(fn (Absence $record): Heroicon => $record->hasDocument() ? Heroicon::OutlinedDocumentCheck : Heroicon::OutlinedExclamationCircle)
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
                        'missing' => $query->whereNull('document'),
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
