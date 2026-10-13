<?php

namespace App\Filament\Admin\Resources\EmployeeChangeRequests;

use App\Enums\EducationLevel;
use App\Enums\NavigationGroup;
use App\Enums\RequestStatus;
use App\Filament\Admin\Resources\EmployeeChangeRequests\Pages\ManageEmployeeChangeRequests;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Models\Country;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Changes employees made to their own personal details, waiting for an administrator.
 */
class EmployeeChangeRequestResource extends Resource
{
    protected static ?string $model = EmployeeChangeRequest::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'employee-change-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.employee_requests.changes_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.employee_requests.changes_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = EmployeeChangeRequest::query()->where('status', RequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function fieldLabel(string $field): string
    {
        if (preg_match('/^emergency(\d)_(\w+)$/', $field, $match)) {
            $part = match ($match[2]) {
                'phone' => __('erp.fields.phone'),
                'relation' => __('erp.employees.relation'),
                default => __('erp.fields.'.$match[2]),
            };

            return __("erp.employees.emergency_contact_{$match[1]}").' · '.$part;
        }

        return match ($field) {
            'first_name', 'last_name', 'house_number', 'addition', 'postal_code', 'city' => __('erp.fields.'.$field),
            'street' => __('erp.fields.street_only'),
            'country_id' => __('erp.fields.country'),
            default => __('erp.employees.'.$field),
        };
    }

    public static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) match ($field) {
            'country_id' => Country::options()[(int) $value] ?? $value,
            'date_of_birth' => rescue(fn () => Carbon::parse($value)->format('d/m/Y'), $value, false),
            'education_level' => EducationLevel::tryFrom((string) $value)?->getLabel() ?? $value,
            'mother_tongue' => __('erp.employees.languages.'.$value),
            'national_number' => Employee::formatNationalNumber((string) $value),
            'bank_account' => Employee::formatIban((string) $value),
            default => $value,
        };
    }

    public static function diffTable(EmployeeChangeRequest $request): HtmlString
    {
        $rows = collect($request->new_values)->map(fn (mixed $value, string $field): string => sprintf(
            '<tr><td style="padding:.35rem .5rem;">%s</td><td style="padding:.35rem .5rem;opacity:.7;">%s</td><td style="padding:.35rem .5rem;"><strong>%s</strong></td></tr>',
            e(self::fieldLabel($field)),
            e(self::displayValue($field, $request->old_values[$field] ?? null)),
            e(self::displayValue($field, $value)),
        ))->implode('');

        return new HtmlString('<table style="width:100%;border-collapse:collapse;font-size:.875rem;"><thead><tr><th style="text-align:left;padding:.35rem .5rem;">'.e(__('erp.my_details.field')).'</th><th style="text-align:left;padding:.35rem .5rem;">'.e(__('erp.my_details.current')).'</th><th style="text-align:left;padding:.35rem .5rem;">'.e(__('erp.my_details.new')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'submitter', 'handler']))
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('erp.employee_requests.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPencilSquare)
            ->columns([
                TextColumn::make('employee.last_name')
                    ->label(ucfirst(__('erp.resources.employee.singular')))
                    ->formatStateUsing(fn (EmployeeChangeRequest $record): string => $record->employee?->fullName() ?? '—')
                    ->url(fn (EmployeeChangeRequest $record): ?string => $record->employee ? EmployeeResource::getUrl('view', ['record' => $record->employee]) : null)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('fields')
                    ->label(__('erp.employee_requests.fields'))
                    ->state(fn (EmployeeChangeRequest $record): string => collect(array_keys($record->new_values))->map(fn (string $field): string => self::fieldLabel($field))->implode(', '))
                    ->wrap()
                    ->limit(120),
                TextColumn::make('updated_at')
                    ->label(__('erp.employee_requests.submitted'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (EmployeeChangeRequest $record): ?string => $record->isPending() ? null : trim(($record->handler?->name ?? '').' '.($record->handled_at?->format('d/m/Y') ?? '').($record->rejection_reason ? ' · '.$record->rejection_reason : ''))),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(RequestStatus::class)
                    ->default(RequestStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('review')
                    ->label(fn (EmployeeChangeRequest $record): string => $record->isPending() ? __('erp.employee_requests.review') : __('erp.employee_requests.show'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (EmployeeChangeRequest $record): string => __('erp.employee_requests.changes_of', ['name' => $record->employee?->fullName()]))
                    ->schema(fn (EmployeeChangeRequest $record): array => [Text::make(self::diffTable($record))])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('erp.employee_requests.close'))
                    ->extraModalFooterActions(fn (EmployeeChangeRequest $record): array => $record->isPending() ? [
                        self::approveAction()->cancelParentActions(),
                        self::rejectAction()->cancelParentActions(),
                    ] : []),
                self::approveAction(),
                self::rejectAction(),
                DeleteAction::make(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('erp.employee_requests.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (EmployeeChangeRequest $record): bool => $record->isPending())
            ->action(function (EmployeeChangeRequest $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->approve($admin);
                self::notifyEmployee($record, approved: true);
                Notification::make()->title(__('erp.employee_requests.approved'))->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('erp.employee_requests.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (EmployeeChangeRequest $record): bool => $record->isPending())
            ->schema([TextInput::make('reason')->label(__('erp.employee_requests.reason'))->maxLength(255)])
            ->action(function (EmployeeChangeRequest $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->reject($data['reason'] ?? null, $admin);
                self::notifyEmployee($record, approved: false);
                Notification::make()->title(__('erp.employee_requests.rejected'))->success()->send();
            });
    }

    protected static function notifyEmployee(EmployeeChangeRequest $request, bool $approved): void
    {
        $user = $request->employee?->user;

        if (! $user || ! $user->is_active) {
            return;
        }

        Notification::make()
            ->title(__($approved ? 'erp.employee_requests.notification_approved' : 'erp.employee_requests.notification_rejected', locale: $user->preferredLocale()))
            ->body($request->rejection_reason)
            ->{$approved ? 'success' : 'danger'}()
            ->sendToDatabase($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeChangeRequests::route('/'),
        ];
    }
}
