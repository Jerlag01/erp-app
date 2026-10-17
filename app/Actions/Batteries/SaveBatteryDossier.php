<?php

namespace App\Actions\Batteries;

use App\Models\BatteryDossier;
use App\Models\BatteryDossierBattery;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The rules for saving a battery (604) or Enersys (605) dossier, for the dossier page and the mobile
 * app alike: a finished dossier is locked, the Input fields need "batteries.input" (or
 * "enersys.input"), and the fields of a signed document are locked for everyone but administrators.
 */
class SaveBatteryDossier
{
    /**
     * The fields the technician fills in on site (everything but the Input tab, the floor plan, prices and
     * the invoice/order): what the mobile app may send.
     *
     * @return list<string>
     */
    public static function fieldFields(BatteryDossier $dossier): array
    {
        $fields = array_filter(array_merge(...array_values($dossier::SIGNED_FIELDS)), fn (string $field): bool => ! str_ends_with($field, '_price'));

        return array_values(array_unique([...$fields, 'acid_boxes', 'empty_boxes_returned', 'packages', 'weight_kg', 'photos_remark']));
    }

    /**
     * Saves fields and battery rows sent by the mobile app. Only the technician's fields are taken,
     * minus what the user may not change.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int|string, array<string, mixed>>  $strings  battery rows by position
     */
    public function handle(BatteryDossier $dossier, array $data, array $strings, User $user): BatteryDossier
    {
        abort_unless($dossier->isEditableBy($user), 403);

        $data = $this->allowedData($dossier, array_intersect_key($data, array_flip(self::fieldFields($dossier))), $user);
        $dossier->update($data);
        $this->saveBatteries($dossier, $strings, $user);

        return $dossier->refresh();
    }

    /**
     * Leaves out what the user may not change: the Input fields without the input permission and the
     * fields of documents that are signed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function allowedData(BatteryDossier $dossier, array $data, User $user): array
    {
        if (! Gate::forUser($user)->allows('viewInput', $dossier::class)) {
            $data = array_diff_key($data, array_flip($dossier::INPUT_FIELDS));
        }

        foreach ($dossier::SIGNED_FIELDS as $document => $fields) {
            if ($dossier->isLockedFor($document, $user)) {
                $data = array_diff_key($data, array_flip($fields));
            }
        }

        return $data;
    }

    /**
     * Saves the rows of the new batteries (single-line values, measurements, ID numbers). Only the values
     * that were sent change; measurements stay as they are once the measurement report is signed, and
     * ID numbers need the input permission.
     *
     * @param  array<int|string, array<string, mixed>>  $strings
     */
    public function saveBatteries(BatteryDossier $dossier, array $strings, User $user): void
    {
        $dossier->syncBatteries();
        $cells = max(1, (int) $dossier->cells_per_battery);
        $number = fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;
        $series = fn (mixed $values): array => array_map(fn (int $index): ?float => $number(((array) $values)[$index] ?? null), range(0, $cells - 1));
        $measurementLocked = $dossier->isLockedFor('measurement', $user);
        $mayChangeIds = Gate::forUser($user)->allows('viewInput', $dossier::class);

        foreach ($dossier->batteries as $battery) {
            $row = (array) ($strings[$battery->position] ?? []);

            if ($row === []) {
                continue;
            }

            $values = [];

            if (! $measurementLocked) {
                foreach (['cell_voltages', 'cell_densities', 'floating_voltages'] as $key) {
                    if (array_key_exists($key, $row)) {
                        $values[$key] = $series($row[$key]);
                    }
                }

                if (array_key_exists('float_voltage', $row)) {
                    $values['float_voltage'] = $number($row['float_voltage']);
                }
            }

            if ($mayChangeIds && filled($row['id_number'] ?? null)) {
                $values['id_number'] = mb_substr(trim((string) $row['id_number']), 0, 30);
            }

            foreach (['cable_type', 'cable_section', 'fuse_amperage'] as $key) {
                if (array_key_exists($key, $row)) {
                    $values[$key] = filled($row[$key]) ? mb_substr(trim((string) $row[$key]), 0, 30) : null;
                }
            }

            if (array_key_exists('cable_length_m', $row)) {
                $values['cable_length_m'] = $number($row['cable_length_m']);
            }

            foreach (['cables_positive', 'cables_negative'] as $key) {
                if (array_key_exists($key, $row)) {
                    $values[$key] = is_numeric($row[$key]) ? (int) $row[$key] : null;
                }
            }

            if (array_key_exists('fuse_type', $row)) {
                $values['fuse_type'] = filled($row['fuse_type']) ? mb_substr(trim((string) $row['fuse_type']), 0, 30) : BatteryDossierBattery::DEFAULT_FUSE_TYPE;
            }

            if ($values !== []) {
                $battery->update($values);
            }
        }

        $dossier->unsetRelation('batteries');
    }
}
