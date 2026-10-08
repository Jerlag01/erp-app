<?php

namespace App\Filament\Resources\ItAssets\Pages;

use App\Actions\It\AuditAsset;
use App\Actions\It\CheckinAsset;
use App\Actions\It\CheckoutAsset;
use App\Filament\Resources\It\ItTargetFields;
use App\Filament\Resources\ItAssets\ItAssetResource;
use App\Models\ItAsset;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
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
                ])
                ->action(fn (ItAsset $record, array $data) => app(CheckoutAsset::class)->handle($record, CheckoutAsset::resolveTarget($data), $data['expected_checkin'] ?? null, $data['note'] ?? null))
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
                ->action(fn (ItAsset $record, array $data) => app(CheckinAsset::class)->handle($record, (int) $data['it_status_label_id'], isset($data['location_id']) ? (int) $data['location_id'] : null, $data['note'] ?? null))
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
}
