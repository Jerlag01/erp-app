<?php

namespace App\Filament\Resources\BaeOrders\Pages;

use App\Actions\Batteries\CreateBaeOrder as StartBaeOrder;
use App\Filament\Resources\BaeOrders\BaeOrderResource;
use App\Filament\Resources\BaeOrders\Schemas\BaeOrderForm;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * For 604 projects made before the module (new ones get their order by themselves): only the project
 * is asked, the order starts with the defaults and is then filled in on the edit page.
 */
class CreateBaeOrder extends CreateRecord
{
    protected static string $resource = BaeOrderResource::class;

    protected function afterFill(): void
    {
        $project = request()->integer('project');

        if ($project && array_key_exists($project, BaeOrderForm::projectOptions())) {
            $this->data['project_id'] = $project;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $project = array_key_exists((int) ($data['project_id'] ?? 0), BaeOrderForm::projectOptions()) ? Project::query()->find($data['project_id']) : null;
        $order = $project ? app(StartBaeOrder::class)->handle($project, auth()->user()) : null;

        if (! $order) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.batteries.bae_order.errors.project')]);
        }

        return $order;
    }

    protected function getRedirectUrl(): string
    {
        return BaeOrderResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
