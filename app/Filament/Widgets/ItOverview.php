<?php

namespace App\Filament\Widgets;

use App\Enums\ItStatusType;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItLicense;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * IT at a glance: assets in use and ready, audits due, licenses expiring, low IT stock.
 */
class ItOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return modules()->it() && (bool) auth()->user()?->hasPermission('it_assets.view');
    }

    protected function getHeading(): ?string
    {
        return 'IT';
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $assets = fn (): Builder => ItAsset::query()->visibleTo($user);
        $stats = [
            Stat::make(__('erp.resources.it_asset.plural'), $assets()->count())
                ->description(__('erp.it.stats.out', ['count' => $assets()->whereNotNull('assigned_type')->count()]))
                ->icon(Heroicon::OutlinedComputerDesktop),
            Stat::make(__('erp.it.stats.ready'), $assets()->whereNull('assigned_type')->whereHas('status', fn (Builder $query) => $query->where('type', ItStatusType::Deployable))->count())
                ->icon(Heroicon::OutlinedCheckCircle),
        ];

        $auditsDue = $assets()->auditDue()->count();
        $stats[] = Stat::make(__('erp.it.audits_due'), $auditsDue)
            ->color($auditsDue > 0 ? 'warning' : 'gray')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck);

        if ($user->hasPermission('it_licenses.view')) {
            $expiring = ItLicense::query()->visibleTo($user)->expiring()->count();
            $stats[] = Stat::make(__('erp.it.expiring'), $expiring)
                ->color($expiring > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedKey);
        }

        if ($user->hasPermission('it_items.view')) {
            $low = ItItem::query()->visibleTo($user)->whereNotNull('min_quantity')->get()->filter->isLow()->count();
            $stats[] = Stat::make(__('erp.it.stats.low_items'), $low)
                ->color($low > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArchiveBoxXMark);
        }

        return $stats;
    }
}
