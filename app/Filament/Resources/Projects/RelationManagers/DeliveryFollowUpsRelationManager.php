<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\DeliveryFollowUp;
use App\Models\Project;
use App\Policies\DeliveryFollowUpPolicy;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The followed-up deliveries of the project (Enersys batteries or electrical boards), read-only: they
 * are filled in on their own lists.
 */
class DeliveryFollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryFollowUps';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedTruck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.follow_ups.tab');
    }

    /**
     * @return list<string>
     */
    protected static function visibleKinds(): array
    {
        $user = auth()->user();

        return $user ? array_values(array_filter([DeliveryFollowUp::ENERSYS, DeliveryFollowUp::BOARD], fn (string $kind): bool => DeliveryFollowUpPolicy::allows($user, $kind, 'view'))) : [];
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return ($kinds = self::visibleKinds()) !== [] && $ownerRecord->deliveryFollowUps()->whereIn('kind', $kinds)->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('kind', self::visibleKinds())->with('supplier'))
            ->columns([
                TextColumn::make('supplier_name')
                    ->label(__('erp.follow_ups.supplier'))
                    ->state(fn (DeliveryFollowUp $record): string => $record->isEnersys() ? 'Enersys' : (string) $record->supplier?->name),
                TextColumn::make('po_number')->label(__('erp.follow_ups.po_number'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('delivery_date')->label(__('erp.follow_ups.delivery_date'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('delivered_on')->label(__('erp.follow_ups.actual_delivery'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('configured_on')->label(__('erp.follow_ups.configured_on'))->date('d/m/Y')->placeholder('—'),
                IconColumn::make('done')
                    ->label(__('erp.follow_ups.delivered').' / '.__('erp.follow_ups.completed'))
                    ->state(fn (DeliveryFollowUp $record): bool => $record->isConfigured() ? $record->completed : $record->delivered)
                    ->boolean(),
            ]);
    }
}
