<?php

namespace App\Filament\Resources\ItAssets\RelationManagers;

use App\Actions\It\CheckoutLicenseSeat;
use App\Models\ItAsset;
use App\Models\ItLicense;
use App\Models\ItLicenseSeat;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Licenses installed on this asset.
 */
class LicenseSeatsRelationManager extends RelationManager
{
    protected static string $relationship = 'licenseSeats';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedKey;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.it_license.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return modules()->it() && (bool) auth()->user()?->hasPermission('it_licenses.view');
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
            ->modifyQueryUsing(fn ($query) => $query->with('license'))
            ->columns([
                TextColumn::make('license.name')
                    ->label(__('erp.fields.name')),
                TextColumn::make('license.expiration_date')
                    ->label(__('erp.fields.expiration_date'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('assigned_at')
                    ->label(__('erp.it.since_label'))
                    ->date('d/m/Y'),
            ])
            ->headerActions([
                Action::make('assignLicense')
                    ->label(__('erp.it.assign_license'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canCheckout)
                    ->schema([
                        Select::make('it_license_id')
                            ->label(__('erp.resources.it_license.singular'))
                            ->options(fn (): array => ItLicense::query()->orderBy('name')->get()
                                ->filter(fn (ItLicense $license): bool => $license->freeSeats() > 0)
                                ->mapWithKeys(fn (ItLicense $license): array => [$license->id => "{$license->name} ({$license->freeSeats()})"])->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var ItAsset $asset */
                        $asset = $this->getOwnerRecord();
                        app(CheckoutLicenseSeat::class)->handle(ItLicense::findOrFail($data['it_license_id']), $asset);
                    })
                    ->successNotificationTitle(__('erp.it.checked_out_done')),
            ])
            ->recordActions([
                Action::make('checkin')
                    ->label(__('erp.it.checkin'))
                    ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (ItLicenseSeat $record): bool => $canCheckout() && $record->license->reassignable)
                    ->requiresConfirmation()
                    ->action(fn (ItLicenseSeat $record) => app(CheckoutLicenseSeat::class)->checkin($record))
                    ->successNotificationTitle(__('erp.it.checked_in_done')),
            ]);
    }
}
