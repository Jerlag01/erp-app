<?php

namespace App\Filament\Resources\ItLicenses\RelationManagers;

use App\Actions\It\CheckoutAsset;
use App\Actions\It\CheckoutLicenseSeat;
use App\Filament\Resources\It\ItTargetFields;
use App\Models\ItLicense;
use App\Models\ItLicenseSeat;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The seats of a license and who or what uses them.
 */
class SeatsRelationManager extends RelationManager
{
    protected static string $relationship = 'licenseSeats';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.fields.seats');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $canCheckout = fn (): bool => (bool) auth()->user()?->hasPermission('it_licenses.checkout');

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'asset']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')
                    ->label(__('erp.it.seat'))
                    ->rowIndex(),
                TextColumn::make('assignee')
                    ->label(__('erp.it.checked_out_to'))
                    ->state(fn (ItLicenseSeat $record): ?string => $record->user?->name ?? $record->asset?->label())
                    ->icon(fn (ItLicenseSeat $record): ?Heroicon => $record->user ? Heroicon::OutlinedUser : ($record->asset ? Heroicon::OutlinedComputerDesktop : null))
                    ->placeholder(__('erp.it.free')),
                TextColumn::make('assigned_at')
                    ->label(__('erp.it.since_label'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(__('erp.fields.note'))
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('used')
                    ->label(__('erp.it.in_use'))
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNotNull('user_id')->orWhereNotNull('it_asset_id')),
                        false: fn (Builder $query) => $query->whereNull('user_id')->whereNull('it_asset_id'),
                    ),
            ])
            ->headerActions([
                Action::make('checkoutSeat')
                    ->label(__('erp.it.checkout'))
                    ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                    ->visible($canCheckout)
                    ->disabled(fn (): bool => $this->getOwnerRecord()->freeSeats() === 0)
                    ->schema([
                        ...ItTargetFields::make(['user', 'asset']),
                        TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var ItLicense $license */
                        $license = $this->getOwnerRecord();
                        app(CheckoutLicenseSeat::class)->handle($license, CheckoutAsset::resolveTarget($data), $data['note'] ?? null);
                    })
                    ->successNotificationTitle(__('erp.it.checked_out_done')),
            ])
            ->recordActions([
                Action::make('checkin')
                    ->label(__('erp.it.checkin'))
                    ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (ItLicenseSeat $record): bool => $canCheckout() && ! $record->isFree() && $this->getOwnerRecord()->reassignable)
                    ->requiresConfirmation()
                    ->action(fn (ItLicenseSeat $record) => app(CheckoutLicenseSeat::class)->checkin($record))
                    ->successNotificationTitle(__('erp.it.checked_in_done')),
            ]);
    }
}
