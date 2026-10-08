<?php

namespace App\Filament\App\Pages;

use App\Enums\NavigationGroup;
use App\Models\ItAsset;
use App\Models\ItItemAssignment;
use App\Models\ItLicenseSeat;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Everyone's own IT: the assets, licenses and accessories checked out to them.
 * Needs no permission, like "View assigned assets" in Snipe-IT.
 */
class MyIt extends Page
{
    protected string $view = 'filament.app.my-it';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'my-it';

    public static function canAccess(): bool
    {
        return modules()->it() && auth()->check();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.my.it');
    }

    public function getTitle(): string
    {
        return __('erp.my.it');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'assets' => ItAsset::query()->where('assigned_type', User::class)->where('assigned_id', $user->id)->with(['model.manufacturer', 'status', 'children.model'])->orderBy('asset_tag')->get(),
            'seats' => ItLicenseSeat::query()->where('user_id', $user->id)->with('license')->get(),
            'items' => ItItemAssignment::query()->where('user_id', $user->id)->whereNull('returned_at')->with('item')->get(),
        ];
    }
}
