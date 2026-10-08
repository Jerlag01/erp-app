<?php

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'employed' => Tab::make(__('erp.employees.employed'))
                ->badge(Employee::query()->employed()->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('left_on')),
            'left' => Tab::make(__('erp.employees.left'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('left_on')),
            'all' => Tab::make(__('erp.employees.all')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'employed';
    }
}
