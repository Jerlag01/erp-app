<?php

namespace App\Actions\Batteries;

use App\Enums\BatteryMounting;
use App\Models\BaeOrder;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use App\Models\User;

/**
 * Starts the BAE order of a 604 project (one per project) with the defaults of Settings > Batteries
 * and, when the project already has a battery dossier, its new batteries.
 */
class CreateBaeOrder
{
    public function appliesTo(Project $project): bool
    {
        return modules()->batteries() && $project->category?->code === settings()->get(BatteryDossier::CATEGORY_SETTING);
    }

    public function handle(Project $project, ?User $by = null): ?BaeOrder
    {
        if (! $this->appliesTo($project)) {
            return null;
        }

        if ($existing = BaeOrder::query()->where('project_id', $project->id)->first()) {
            return $existing;
        }

        $settings = (array) settings()->get('batteries');
        $order = new BaeOrder([
            'project_id' => $project->id,
            'order_date' => today(),
            'contract' => $settings['bae_contract'] ?? null,
            'delivery_date' => $settings['bae_delivery_date'] ?? null,
            'delivery_address' => $settings['bae_delivery_address'] ?? null,
            'contact_name' => $settings['bae_contact_name'] ?? null,
            'contact_phone' => $settings['bae_contact_phone'] ?? null,
            'contact_fax' => $settings['bae_contact_fax'] ?? null,
            'contact_mobile' => $settings['bae_contact_mobile'] ?? null,
            'contact_email' => $settings['bae_contact_email'] ?? null,
            'billing_block' => $settings['bae_billing_block'] ?? null,
            'remarks' => $settings['bae_remarks'] ?? null,
            'lines' => BaeOrder::linesFor(null),
            'created_by' => $by?->id,
        ]);
        $order->setRelation('project', $project);

        if ($dossier = BatteryDossier::query()->where('project_id', $project->id)->latest('id')->first()) {
            $order->fill(self::fromDossier($dossier));
        }

        $order->order_number = BaeOrder::defaultOrderNumber($order, $by);
        $order->save();

        return $order;
    }

    /**
     * The new batteries of the dossier (Input tab) with the lines and prices of their model and mounting.
     *
     * @return array<string, mixed>
     */
    public static function fromDossier(BatteryDossier $dossier): array
    {
        $values = array_filter([
            'battery_model_id' => $dossier->battery_model_id,
            'mounting' => $dossier->mounting?->value,
            'battery_count' => $dossier->new_count ?: null,
            'cells_per_battery' => $dossier->cells_per_battery ?: null,
            'volts_per_cell' => $dossier->volts_per_cell ?: null,
            'capacity_ah' => $dossier->new_capacity_ah,
            'technology' => $dossier->new_technology?->value,
        ], fn (mixed $value): bool => filled($value));

        return [...self::fromCatalogue($values['battery_model_id'] ?? null, $values['mounting'] ?? null), ...$values];
    }

    /**
     * The BAE price per cell of the model, and the lines (connectors, rack) of its mounting.
     *
     * @return array<string, mixed>
     */
    public static function fromCatalogue(?int $modelId, ?string $mounting): array
    {
        $model = $modelId ? BatteryModel::query()->with('racks')->find($modelId) : null;

        if (! $model) {
            return [];
        }

        $rack = $model->rackFor(BatteryMounting::tryFrom((string) $mounting));

        return array_filter([
            'cell_price' => $model->bae_cell_price !== null ? (float) $model->bae_cell_price : null,
            'technology' => $model->technology?->value,
        ], fn (mixed $value): bool => $value !== null) + ['lines' => BaeOrder::linesFor($rack)];
    }
}
