<?php

namespace App\Filament\Admin\Resources\EmployeeCertificateRequests;

use App\Enums\NavigationGroup;
use App\Enums\RequestStatus;
use App\Filament\Admin\Resources\EmployeeCertificateRequests\Pages\ManageEmployeeCertificateRequests;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Models\EmployeeCertificateRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Certificates employees added themselves. Approving adds them to the register (or renews the
 * certificate of the same type).
 */
class EmployeeCertificateRequestResource extends Resource
{
    protected static ?string $model = EmployeeCertificateRequest::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'employee-certificate-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.employee_requests.certificates_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.employee_requests.certificates_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = EmployeeCertificateRequest::query()->where('status', RequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'handler']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.employee_requests.empty'))
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)
            ->columns([
                TextColumn::make('employee.last_name')
                    ->label(ucfirst(__('erp.resources.employee.singular')))
                    ->formatStateUsing(fn (EmployeeCertificateRequest $record): string => $record->employee?->fullName() ?? '—')
                    ->url(fn (EmployeeCertificateRequest $record): ?string => $record->employee ? EmployeeResource::getUrl('view', ['record' => $record->employee]) : null)
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label(__('erp.employees.certificate'))
                    ->state(fn (EmployeeCertificateRequest $record): string => $record->label())
                    ->wrap(),
                TextColumn::make('obtained_on')->label(__('erp.employees.obtained_on'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('expires_on')->label(__('erp.employees.expires_on'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('document_name')
                    ->label(__('erp.employees.certificate_pdf'))
                    ->placeholder('—')
                    ->url(fn (EmployeeCertificateRequest $record): ?string => filled($record->document) ? route('employees.document', ['employee' => $record->employee_id, 'kind' => 'request', 'id' => $record->id]) : null, shouldOpenInNewTab: true),
                TextColumn::make('created_at')->label(__('erp.employee_requests.submitted'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (EmployeeCertificateRequest $record): ?string => $record->isPending() ? null : trim(($record->handler?->name ?? '').' '.($record->handled_at?->format('d/m/Y') ?? '').($record->rejection_reason ? ' · '.$record->rejection_reason : ''))),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(RequestStatus::class)
                    ->default(RequestStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('erp.employee_requests.approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (EmployeeCertificateRequest $record): string => $record->employee?->certificates()->where('type', $record->type)->exists()
                        ? __('erp.employee_requests.approve_renews')
                        : __('erp.employee_requests.approve_adds'))
                    ->visible(fn (EmployeeCertificateRequest $record): bool => $record->isPending())
                    ->action(function (EmployeeCertificateRequest $record): void {
                        /** @var User $admin */
                        $admin = auth()->user();
                        $record->approve($admin);
                        self::notifyEmployee($record, approved: true);
                        Notification::make()->title(__('erp.employee_requests.approved'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('erp.employee_requests.reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (EmployeeCertificateRequest $record): bool => $record->isPending())
                    ->schema([TextInput::make('reason')->label(__('erp.employee_requests.reason'))->maxLength(255)])
                    ->action(function (EmployeeCertificateRequest $record, array $data): void {
                        /** @var User $admin */
                        $admin = auth()->user();
                        $record->reject($data['reason'] ?? null, $admin);
                        self::notifyEmployee($record, approved: false);
                        Notification::make()->title(__('erp.employee_requests.rejected'))->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    protected static function notifyEmployee(EmployeeCertificateRequest $request, bool $approved): void
    {
        $user = $request->employee?->user;

        if (! $user || ! $user->is_active) {
            return;
        }

        Notification::make()
            ->title(__($approved ? 'erp.employee_requests.certificate_approved' : 'erp.employee_requests.certificate_rejected', ['certificate' => $request->label()], $user->preferredLocale()))
            ->body($request->rejection_reason)
            ->{$approved ? 'success' : 'danger'}()
            ->sendToDatabase($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeCertificateRequests::route('/'),
        ];
    }
}
