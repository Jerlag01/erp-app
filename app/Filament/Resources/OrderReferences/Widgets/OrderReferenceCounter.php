<?php

namespace App\Filament\Resources\OrderReferences\Widgets;

use App\Models\OrderReference;
use App\Models\OrderReferenceCounter as Counter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

/**
 * Shows the last reference taken and what the next one will look like for the signed-in user.
 */
class OrderReferenceCounter extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    #[On('order-reference-taken')]
    public function refreshCounter(): void {}

    protected function getStats(): array
    {
        $now = now();
        $last = OrderReference::query()->where('year', $now->year)->orderByDesc('number')->first();
        $next = Counter::lastNumber($now->year) + 1;

        return [
            Stat::make(__('erp.orders.last'), $last?->reference ?? '—')
                ->description($last ? __('erp.orders.last_by', ['name' => $last->user?->name ?? '—', 'date' => $last->created_at->format('d/m/Y H:i')]) : null)
                ->icon(Heroicon::OutlinedHashtag),
            Stat::make(__('erp.orders.next_preview'), OrderReference::format($next, $now->month, $now->year, auth()->user()->referenceInitials()))
                ->description(__('erp.orders.next_help'))
                ->icon(Heroicon::OutlinedPlusCircle),
            Stat::make(__('erp.orders.this_year', ['year' => $now->year]), (string) OrderReference::query()->where('year', $now->year)->count())
                ->icon(Heroicon::OutlinedDocumentDuplicate),
        ];
    }
}
