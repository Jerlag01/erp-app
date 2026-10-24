<?php

namespace App\Filament\Resources\BoardDeliveries\Pages;

use App\Filament\Resources\BoardDeliveries\BoardDeliveryResource;
use App\Models\BoardSupplier;
use App\Models\DeliveryFollowUp;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * One tab (follow-up list) per active board supplier.
 */
class ManageBoardDeliveries extends ManageRecords
{
    protected static string $resource = BoardDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [
                ...$data,
                'kind' => DeliveryFollowUp::BOARD,
                'created_by' => auth()->id(),
                'completed_at' => ($data['completed'] ?? false) ? now() : null,
            ]),
        ];
    }

    public function getTabs(): array
    {
        return BoardSupplier::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (BoardSupplier $supplier): array => ['supplier-'.$supplier->id => Tab::make($supplier->name)
                ->badge(fn (): ?int => DeliveryFollowUp::query()->kind(DeliveryFollowUp::BOARD)->where('board_supplier_id', $supplier->id)->visibleTo(auth()->user())
                    ->where($supplier->isConfigured() ? 'completed' : 'delivered', false)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('board_supplier_id', $supplier->id))])
            ->all();
    }
}
