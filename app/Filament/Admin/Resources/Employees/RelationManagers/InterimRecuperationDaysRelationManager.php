<?php

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\InterimRecuperationDay;
use App\Support\RecuperationDays;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The IR days (inhaalrust) and public holidays of an interim, adjusted freely: company IR days and public
 * holidays taken away (they are not entitled and work that day) and IR days of their own on any working
 * day. Deleting a line undoes it. The prestation sheet, the planning, the leave counts and Outlook follow.
 */
class InterimRecuperationDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'interimRecuperationDays';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Employee && $ownerRecord->is_interim && $ownerRecord->user_id !== null;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.recuperation_days.interim.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('erp.recuperation_days.description'))->placeholder(__('erp.recuperation_days.description_placeholder'))->maxLength(100),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->description(fn (): string => $this->summary())
            ->emptyStateHeading(__('erp.recuperation_days.interim.empty'))
            ->emptyStateDescription(__('erp.recuperation_days.interim.empty_help'))
            ->columns([
                TextColumn::make('date')->label(__('erp.fields.date'))->date('D d/m/Y')->sortable(),
                TextColumn::make('is_day_off')
                    ->label(__('erp.recuperation_days.interim.kind'))
                    ->formatStateUsing(fn (bool $state, InterimRecuperationDay $record): string => __('erp.recuperation_days.interim.'.($state ? 'own' : ($record->companyDay()?->isRecuperation() === false ? 'holiday_taken_away' : 'taken_away'))))
                    ->badge()
                    ->color(fn (bool $state, InterimRecuperationDay $record): string => $state ? 'success' : ($record->companyDay()?->isRecuperation() === false ? 'warning' : 'gray')),
                TextColumn::make('name')
                    ->label(__('erp.recuperation_days.description'))
                    ->state(fn (InterimRecuperationDay $record): ?string => $record->is_day_off ? $record->name : $record->companyDay()?->name)
                    ->placeholder('—'),
                TextColumn::make('creator.name')->label(__('erp.recuperation_days.interim.by'))->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label(__('erp.recuperation_days.interim.assign'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalDescription(__('erp.recuperation_days.interim.assign_help'))
                    ->schema([
                        Repeater::make('days')
                            ->hiddenLabel()
                            ->schema([
                                DatePicker::make('date')->label(__('erp.fields.date'))->native(false)->displayFormat('d/m/Y')->required(),
                                TextInput::make('name')->label(__('erp.recuperation_days.description'))->placeholder(__('erp.recuperation_days.description_placeholder'))->maxLength(100),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel(__('erp.recuperation_days.interim.add_date')),
                    ])
                    ->action(fn (array $data) => $this->assign($data['days'])),
                Action::make('takeAway')
                    ->label(__('erp.recuperation_days.interim.take_away'))
                    ->icon(Heroicon::OutlinedMinus)
                    ->color('gray')
                    ->modalDescription(__('erp.recuperation_days.interim.take_away_help'))
                    ->schema([
                        CheckboxList::make('dates')
                            ->hiddenLabel()
                            ->options(fn (): array => $this->companyDaysToTakeAway())
                            ->helperText(fn (): ?string => $this->companyDaysToTakeAway() === [] ? __('erp.recuperation_days.interim.none_to_take_away') : null)
                            ->columns(2)
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->takeAway($data['dates'])),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (InterimRecuperationDay $record): bool => $record->is_day_off),
                DeleteAction::make()->label(__('erp.recuperation_days.interim.undo')),
            ]);
    }

    /**
     * "IR days in 2026 (2): Mon 02/11, Fri 13/11 · Public holidays in 2026 (9): …" for this interim.
     */
    protected function summary(): string
    {
        $year = today()->year;
        $userId = (int) $this->getOwnerRecord()->user_id;
        $list = fn (array $dates): string => collect($dates)->map(fn (string $date): string => Carbon::parse($date)->translatedFormat('D d/m'))->implode(', ') ?: '—';
        $recuperation = array_keys(RecuperationDays::forUser($userId, "{$year}-01-01", "{$year}-12-31"));
        $holidays = array_keys(RecuperationDays::publicHolidaysFor($userId, "{$year}-01-01", "{$year}-12-31"));

        return __('erp.recuperation_days.interim.summary', ['year' => $year, 'count' => count($recuperation), 'days' => $list($recuperation)])
            .' · '.__('erp.recuperation_days.interim.summary_holidays', ['year' => $year, 'count' => count($holidays), 'days' => $list($holidays)]);
    }

    /**
     * Company IR days and public holidays from the start of this year on that still apply to the interim.
     *
     * @return array<string, string>
     */
    protected function companyDaysToTakeAway(): array
    {
        $taken = InterimRecuperationDay::query()->where('user_id', $this->getOwnerRecord()->user_id)->where('is_day_off', false)
            ->pluck('date')->map(fn ($date): string => $date->toDateString())->all();

        return Holiday::query()->whereDate('date', '>=', today()->startOfYear())->orderBy('date')->get()
            ->reject(fn (Holiday $holiday): bool => in_array($holiday->date->toDateString(), $taken, true))
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->date->translatedFormat('D d/m/Y').' · '.$holiday->label()])
            ->all();
    }

    /**
     * @param  list<array{date: string, name?: ?string}>  $days
     */
    protected function assign(array $days): void
    {
        $userId = (int) $this->getOwnerRecord()->user_id;
        $added = 0;
        $skipped = [];

        foreach ($days as $day) {
            $date = Carbon::parse($day['date'])->toDateString();
            $existing = InterimRecuperationDay::query()->where('user_id', $userId)->whereDate('date', $date)->first();
            $holiday = Holiday::query()->whereDate('date', $date)->first();

            // A company IR day or public holiday taken away earlier: giving it back undoes that.
            if ($holiday !== null && $existing && ! $existing->is_day_off) {
                $existing->delete();
                $added++;

                continue;
            }

            // Weekends, public holidays and days that already are an IR day for them.
            if (Carbon::parse($date)->isWeekend() || $holiday !== null || $existing !== null) {
                $skipped[] = Carbon::parse($date)->format('d/m/Y');

                continue;
            }

            InterimRecuperationDay::query()->create(['user_id' => $userId, 'date' => $date, 'is_day_off' => true, 'name' => $day['name'] ?? null, 'created_by' => auth()->id()]);
            $added++;
        }

        $this->notify($added, $skipped);
    }

    /**
     * @param  list<string>  $dates
     */
    protected function takeAway(array $dates): void
    {
        $userId = (int) $this->getOwnerRecord()->user_id;
        $added = 0;

        foreach ($dates as $date) {
            if (! Holiday::query()->whereDate('date', $date)->exists()) {
                continue;
            }

            InterimRecuperationDay::query()->updateOrCreate(['user_id' => $userId, 'date' => $date], ['is_day_off' => false, 'name' => null, 'created_by' => auth()->id()]);
            $added++;
        }

        $this->notify($added, []);
    }

    /**
     * @param  list<string>  $skipped
     */
    protected function notify(int $count, array $skipped): void
    {
        Notification::make()
            ->title(trans_choice('erp.recuperation_days.interim.changed', $count, ['count' => $count]))
            ->body($skipped === [] ? null : __('erp.recuperation_days.interim.skipped', ['dates' => implode(', ', $skipped)]))
            ->{$count > 0 ? 'success' : 'warning'}()
            ->send();
    }
}
