<?php

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Actions\Employees\EndEmployment;
use App\Actions\Employees\SendWelcomeMail;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->fullName();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // Dismissed with a notice period (opzeggingstermijn): sollicitatieverlof until the end date, then
            // "no longer in service" follows by itself (employees:end-notice).
            Action::make('notice')
                ->label(fn (Employee $record): string => $record->isInNotice() ? __('erp.employees.notice.change') : __('erp.employees.notice.start'))
                ->icon(Heroicon::OutlinedClock)
                ->color('warning')
                ->visible(fn (Employee $record): bool => $record->isEmployed())
                ->modalDescription(__('erp.employees.notice.help'))
                ->fillForm(fn (Employee $record): array => ['notice_started_on' => $record->notice_started_on?->toDateString() ?? today()->toDateString(), 'notice_ends_on' => $record->notice_ends_on?->toDateString()])
                ->schema([
                    DatePicker::make('notice_started_on')->label(__('erp.employees.notice.started_on'))->native(false)->displayFormat('d/m/Y')->required(),
                    DatePicker::make('notice_ends_on')->label(__('erp.employees.notice.ends_on'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('notice_started_on')->required(),
                ])
                ->action(fn (Employee $record, array $data) => $record->update(['notice_started_on' => $data['notice_started_on'], 'notice_ends_on' => $data['notice_ends_on']]))
                ->successNotificationTitle(__('erp.employees.notice.saved')),
            Action::make('cancelNotice')
                ->label(__('erp.employees.notice.cancel'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (Employee $record): bool => $record->isInNotice())
                ->requiresConfirmation()
                ->action(fn (Employee $record) => $record->update(['notice_started_on' => null, 'notice_ends_on' => null]))
                ->successNotificationTitle(__('erp.employees.notice.cancelled')),
            Action::make('endEmployment')
                ->label(__('erp.employees.end_employment'))
                ->icon(Heroicon::OutlinedUserMinus)
                ->color('danger')
                ->visible(fn (Employee $record): bool => $record->isEmployed())
                ->modalDescription(__('erp.employees.end_employment_help'))
                ->schema([
                    DatePicker::make('left_on')
                        ->label(__('erp.employees.left_on'))
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(today())
                        ->required(),
                    TextInput::make('leaving_reason')
                        ->label(__('erp.employees.leaving_reason'))
                        ->maxLength(255),
                ])
                ->action(function (Employee $record, array $data): void {
                    app(EndEmployment::class)->handle($record, $data['left_on'], $data['leaving_reason'] ?? null);
                    $record->refresh();
                })
                ->successNotificationTitle(__('erp.employees.employment_ended')),
            Action::make('rehire')
                ->label(__('erp.employees.rehire'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->visible(fn (Employee $record): bool => ! $record->isEmployed())
                ->requiresConfirmation()
                ->modalDescription(__('erp.employees.rehire_help'))
                ->action(function (Employee $record): void {
                    app(EndEmployment::class)->rehire($record);
                    $record->refresh();
                })
                ->successNotificationTitle(__('erp.employees.rehired')),
            ActionGroup::make([
                Action::make('resendWelcome')
                    ->label(__('erp.employees.resend_welcome'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (Employee $record): bool => $record->isEmployed() && (bool) $record->user?->is_active)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Employee $record): string => __('erp.employees.resend_welcome_help', ['email' => $record->user->email]))
                    ->action(function (Employee $record): void {
                        app(SendWelcomeMail::class)->handle($record)
                            ? Notification::make()->title(__('erp.employees.welcome_sent', ['email' => $record->user->email]))->success()->send()
                            : Notification::make()->title(__('erp.employees.welcome_failed'))->body(__('erp.employees.welcome_failed_help'))->danger()->send();
                    }),
                Action::make('account')
                    ->label(__('erp.employees.open_account'))
                    ->icon(Heroicon::OutlinedKey)
                    ->visible(fn (Employee $record): bool => $record->user !== null)
                    ->url(fn (Employee $record): string => UserResource::getUrl('edit', ['record' => $record->user])),
                DeleteAction::make()
                    ->modalDescription(__('erp.employees.delete_help')),
            ]),
        ];
    }
}
