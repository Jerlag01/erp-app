<?php

namespace App\Filament\App\Resources\MyAbsences\Pages;

use App\Enums\AbsenceType;
use App\Filament\App\Resources\MyAbsences\MyAbsenceResource;
use App\Models\Absence;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMyAbsences extends ListRecords
{
    protected static string $resource = MyAbsenceResource::class;

    /**
     * One list per kind of absence: medical, overtime as leave, family leave.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $days = Absence::query()->where('user_id', auth()->id())->selectRaw('type, sum(days) as total')->groupBy('type')->pluck('total', 'type');
        $tabs = [];

        foreach (AbsenceType::cases() as $type) {
            $tabs[$type->value] = Tab::make($type->getLabel())
                ->icon($type->getIcon())
                ->badge(($total = (float) ($days[$type->value] ?? 0)) > 0 ? rtrim(rtrim(number_format($total, 1, ',', ''), '0'), ',').' '.__('erp.absences.days_short') : null)
                ->badgeColor($type->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->value));
        }

        return $tabs;
    }
}
