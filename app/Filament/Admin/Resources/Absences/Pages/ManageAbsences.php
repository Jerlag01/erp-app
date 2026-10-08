<?php

namespace App\Filament\Admin\Resources\Absences\Pages;

use App\Enums\AbsenceType;
use App\Filament\Admin\Resources\Absences\AbsenceResource;
use App\Models\Absence;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageAbsences extends ManageRecords
{
    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // A new absence starts with the type of the open tab.
                ->fillForm(fn (): array => ['type' => AbsenceType::tryFrom((string) $this->activeTab)?->value ?? AbsenceType::Medical->value])
                ->mutateDataUsing(fn (array $data): array => [...AbsenceResource::withDocumentSource($data), 'created_by' => auth()->id()]),
        ];
    }

    /**
     * One list per type.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Absence::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $tabs = [];

        foreach (AbsenceType::cases() as $type) {
            $tabs[$type->value] = Tab::make($type->getLabel())
                ->icon($type->getIcon())
                ->badge((int) ($counts[$type->value] ?? 0) ?: null)
                ->badgeColor($type->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->value));
        }

        $tabs['all'] = Tab::make(__('erp.absences.all'));

        return $tabs;
    }
}
