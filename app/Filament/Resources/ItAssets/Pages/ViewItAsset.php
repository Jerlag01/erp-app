<?php

namespace App\Filament\Resources\ItAssets\Pages;

use App\Actions\It\AuditAsset;
use App\Actions\It\CheckinAsset;
use App\Actions\It\CheckoutAsset;
use App\Actions\It\ItDeliveryReports;
use App\Filament\Forms\SignaturePad;
use App\Filament\Resources\It\ItTargetFields;
use App\Filament\Resources\ItAssets\ItAssetResource;
use App\Models\ItAsset;
use App\Models\ItDeliveryReport;
use App\Models\ToolboxMeeting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ViewItAsset extends ViewRecord
{
    protected static string $resource = ItAssetResource::class;

    protected function getHeaderActions(): array
    {
        $can = fn (string $ability): bool => (bool) auth()->user()?->hasPermission("it_assets.{$ability}");

        return [
            Action::make('checkout')
                ->label(__('erp.it.checkout'))
                ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                ->color('primary')
                ->visible(fn (ItAsset $record): bool => $can('checkout') && ! $record->isCheckedOut())
                ->disabled(fn (ItAsset $record): bool => ! $record->isDeployable())
                ->tooltip(fn (ItAsset $record): ?string => $record->isDeployable() ? null : __('erp.it.errors.not_deployable', ['status' => $record->status?->name]))
                ->modalHeading(fn (ItAsset $record): string => __('erp.it.checkout').': '.$record->label())
                ->schema(fn (ItAsset $record): array => [
                    ...ItTargetFields::make(except: $record),
                    DatePicker::make('expected_checkin')
                        ->label(__('erp.it.expected_checkin'))
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(today()),
                    TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                    self::deliveryReportSection(),
                ])
                ->fillForm(fn (ItAsset $record): array => ['delivery_report' => true, 'report' => ItDeliveryReports::defaults($record, null)])
                ->action(function (ItAsset $record, array $data): void {
                    $target = CheckoutAsset::resolveTarget($data);
                    app(CheckoutAsset::class)->handle($record, $target, $data['expected_checkin'] ?? null, $data['note'] ?? null);

                    // Handed to an employee: the delivery report to sign, with the employee's details where left empty.
                    if ($target instanceof User && ($data['delivery_report'] ?? false)) {
                        $details = array_merge(
                            array_filter(ItDeliveryReports::defaults($record, $target), fn (mixed $value): bool => filled($value)),
                            array_filter((array) ($data['report'] ?? []), fn (mixed $value): bool => filled($value) || is_bool($value)),
                        );
                        app(ItDeliveryReports::class)->issue($record, $target, $details, auth()->user(), $data['company_signature'] ?? null);
                    }
                })
                ->after(fn (ItAsset $record) => $record->refresh())
                ->successNotificationTitle(__('erp.it.checked_out_done')),
            Action::make('checkin')
                ->label(__('erp.it.checkin'))
                ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
                ->color('success')
                ->visible(fn (ItAsset $record): bool => $can('checkout') && $record->isCheckedOut())
                ->modalHeading(fn (ItAsset $record): string => __('erp.it.checkin').': '.$record->label())
                ->modalDescription(fn (ItAsset $record): string => __('erp.it.checkin_from', ['name' => $record->assignedName()]))
                ->fillForm(fn (ItAsset $record): array => ['it_status_label_id' => $record->it_status_label_id, 'location_id' => $record->location_id])
                ->schema([
                    Select::make('it_status_label_id')
                        ->label(__('erp.fields.status'))
                        ->relationship('status', 'name')
                        ->preload()
                        ->required(),
                    Select::make('location_id')
                        ->label(__('erp.resources.location.singular'))
                        ->relationship('location', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                        ->preload()
                        ->visible(fn (): bool => modules()->locations()),
                    TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                ])
                ->action(function (ItAsset $record, array $data): void {
                    app(CheckinAsset::class)->handle($record, (int) $data['it_status_label_id'], isset($data['location_id']) ? (int) $data['location_id'] : null, $data['note'] ?? null);
                    app(ItDeliveryReports::class)->cancelFor($record);
                })
                ->after(fn (ItAsset $record) => $record->refresh())
                ->successNotificationTitle(__('erp.it.checked_in_done')),
            Action::make('audit')
                ->label(__('erp.it.audit'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->visible(fn (): bool => $can('audit'))
                ->modalDescription(__('erp.it.audit_help'))
                ->fillForm(fn (ItAsset $record): array => [
                    'next_audit_date' => today()->addMonthsNoOverflow(settings()->itAuditMonths())->toDateString(),
                    'location_id' => $record->location_id,
                ])
                ->schema([
                    DatePicker::make('next_audit_date')
                        ->label(__('erp.fields.next_audit_date'))
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(today())
                        ->required(),
                    Select::make('location_id')
                        ->label(__('erp.resources.location.singular'))
                        ->relationship('location', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                        ->preload()
                        ->visible(fn (): bool => modules()->locations()),
                    TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                ])
                ->action(fn (ItAsset $record, array $data) => app(AuditAsset::class)->handle($record, $data['next_audit_date'], isset($data['location_id']) ? (int) $data['location_id'] : null, $data['note'] ?? null))
                ->after(fn (ItAsset $record) => $record->refresh())
                ->successNotificationTitle(__('erp.it.audited')),
            EditAction::make(),
        ];
    }

    /**
     * The delivery report the employee signs (only when the asset goes to a user). Empty job title and
     * place of work are taken from the employee.
     */
    protected static function deliveryReportSection(): Section
    {
        $t = fn (string $key, array $replace = []): string => __('erp.it.delivery_report.'.$key, $replace);
        $company = ToolboxMeeting::companyName();

        return Section::make($t('section'))
            ->description($t('section_help'))
            ->visible(fn (Get $get): bool => $get('target_type') === 'user')
            ->collapsible()
            ->schema([
                Toggle::make('delivery_report')->label($t('make'))->live(),
                Grid::make(2)
                    ->visible(fn (Get $get): bool => (bool) $get('delivery_report'))
                    ->schema([
                        TextInput::make('report.job_title')->label($t('job_title'))->maxLength(100),
                        TextInput::make('report.site')->label($t('site'))->maxLength(150),
                        TextInput::make('report.device')->label($t('device'))->maxLength(150),
                        TextInput::make('report.serial')->label($t('reference'))->maxLength(100),
                        TextInput::make('report.imei')->label($t('imei'))->maxLength(50),
                        TextInput::make('report.phone_number')->label($t('phone_number'))->tel()->maxLength(30),
                        TextInput::make('report.sim')->label($t('sim'))->maxLength(100),
                        TextInput::make('report.accessories')->label($t('accessories'))->maxLength(200),
                        Select::make('report.condition')->label($t('condition'))
                            ->options(collect(ItDeliveryReport::CONDITIONS)->mapWithKeys(fn (string $condition): array => [$condition => $t('conditions.'.$condition)])->all()),
                        Fieldset::make($t('old_device_title'))->columnSpanFull()->columns(2)->schema([
                            TextInput::make('report.old_device')->label($t('old_device'))->maxLength(150),
                            TextInput::make('report.old_imei')->label($t('imei'))->maxLength(50),
                            TextInput::make('report.old_condition')->label($t('old_condition'))->maxLength(150),
                            Select::make('report.old_wiped')->label($t('old_wiped'))->boolean($t('yes'), $t('no')),
                        ]),
                        SignaturePad::make('company_signature')->label($t('company_signature', ['company' => $company]))->columnSpanFull(),
                    ]),
            ]);
    }
}
