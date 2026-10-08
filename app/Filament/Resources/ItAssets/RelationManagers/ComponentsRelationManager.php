<?php

namespace App\Filament\Resources\ItAssets\RelationManagers;

use App\Actions\It\CheckoutItem;
use App\Enums\ItItemKind;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItItemAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Components (RAM, disks…) built into this asset.
 */
class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCpuChip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.it_component.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return modules()->it() && (bool) auth()->user()?->hasPermission('it_items.view');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $canCheckout = fn (): bool => (bool) auth()->user()?->hasPermission('it_items.checkout');

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('item'))
            ->columns([
                TextColumn::make('item.name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (ItItemAssignment $record): ?string => $record->item->serial),
                TextColumn::make('quantity')
                    ->label(__('erp.fields.quantity')),
                TextColumn::make('assigned_at')
                    ->label(__('erp.it.installed_on'))
                    ->date('d/m/Y'),
            ])
            ->headerActions([
                Action::make('install')
                    ->label(__('erp.it.install_component'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canCheckout)
                    ->schema([
                        Select::make('it_item_id')
                            ->label(__('erp.resources.it_component.singular'))
                            ->options(fn (): array => ItItem::query()->kind(ItItemKind::Component)->visibleTo(auth()->user())->orderBy('name')->get()
                                ->filter(fn (ItItem $item): bool => $item->available() > 0)
                                ->mapWithKeys(fn (ItItem $item): array => [$item->id => "{$item->name} ({$item->available()})"])->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')->label(__('erp.fields.quantity'))->numeric()->integer()->minValue(1)->default(1)->required(),
                        TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var ItAsset $asset */
                        $asset = $this->getOwnerRecord();
                        app(CheckoutItem::class)->handle(ItItem::findOrFail($data['it_item_id']), $asset, (int) $data['quantity'], $data['note'] ?? null);
                    })
                    ->successNotificationTitle(__('erp.it.installed')),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('erp.it.remove_component'))
                    ->icon(Heroicon::OutlinedMinus)
                    ->color('danger')
                    ->visible($canCheckout)
                    ->requiresConfirmation()
                    ->action(fn (ItItemAssignment $record) => app(CheckoutItem::class)->checkin($record))
                    ->successNotificationTitle(__('erp.it.removed')),
            ]);
    }
}
