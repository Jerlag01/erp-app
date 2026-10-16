<?php

namespace App\Support;

/**
 * The questions of the "Visueel keuringsverslag" (visual inspection report). Each is answered with yes
 * or no; the keys are stored, so they never change. The texts are in lang/{nl,fr,en}/battery_documents.php
 * and follow the language in use: the language of the user on screen, the language of the region of the
 * COW site on the PDF.
 */
class BatteryInspection
{
    /**
     * BAE (604): section => question keys.
     */
    public const SECTIONS = [
        'cells' => ['cells_identical', 'cells_same_date', 'cells_labelled', 'label_manufacturer', 'label_type', 'label_capacity', 'label_float_voltage', 'label_production_date', 'cells_measurable', 'ceramic_vents', 'recovery_vents'],
        'battery' => ['flexible_connectors', 'connectors_insulated', 'terminal_plates', 'terminal_plates_shielded', 'polarity_marked', 'battery_numbered', 'cells_numbered', 'markings_readable', 'id_stickers', 'cleaned'],
        'setup' => ['stable', 'rack_fits', 'cells_accessible', 'plates_under_rack'],
        'cabinet' => ['doors_open', 'doors_direction', 'door_battery_number', 'door_battery_symbol', 'door_danger_triangle', 'door_voltage'],
        'acid_trays' => ['acid_trays', 'acid_trays_welded', 'acid_trays_clean', 'acid_trays_space'],
        'removal' => ['old_removed', 'old_cells_removed', 'old_parts_removed', 'old_rack_removed', 'old_place_clean', 'old_maintenance_removed', 'old_ppe_removed', 'old_docs_removed', 'old_monitoring_removed'],
        'misc' => ['damage', 'packaging_removed', 'room_cleaned', 'available', 'doc_maintenance', 'doc_installation', 'photos', 'doc_holder', 'measurements_available'],
        'ppe' => ['face_shield', 'apron', 'gloves', 'eye_wash', 'ppe_cabinet'],
        'maintenance' => ['thermometer', 'hydrometer', 'measuring_cup', 'funnel', 'maintenance_rack'],
    ];

    /**
     * Enersys (605, "12V enersys": 12 V monoblocks in a cabinet): a shorter list with the same keys.
     */
    public const ENERSYS_SECTIONS = [
        'cells' => ['cells_identical', 'cells_same_date', 'cells_labelled', 'label_manufacturer', 'label_type', 'label_capacity', 'label_float_voltage', 'label_production_date', 'cells_measurable'],
        'battery' => ['flexible_connectors', 'connectors_insulated', 'polarity_marked', 'battery_numbered', 'cells_numbered', 'markings_readable', 'cleaned'],
        'setup' => ['stable', 'rack_fits', 'cells_accessible'],
        'cabinet' => ['doors_open', 'doors_direction', 'door_battery_number', 'door_battery_symbol', 'door_danger_triangle', 'door_voltage'],
        'removal' => ['old_removed', 'old_cells_removed', 'old_parts_removed', 'old_rack_removed', 'old_place_clean', 'old_accessories_removed', 'old_docs_removed'],
        'misc' => ['damage', 'packaging_removed', 'room_cleaned', 'available', 'documentation_delivered', 'photos', 'measurements_available'],
    ];

    /**
     * Section title => [key => question], in the current language.
     *
     * @return array<string, array<string, string>>
     */
    public static function sections(): array
    {
        return self::translate(self::SECTIONS);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function enersysSections(): array
    {
        return self::translate(self::ENERSYS_SECTIONS);
    }

    /**
     * @param  array<string, list<string>>  $sections
     * @return array<string, array<string, string>>
     */
    protected static function translate(array $sections): array
    {
        $translated = [];

        foreach ($sections as $section => $keys) {
            $translated[__('battery_documents.inspection.sections.'.$section)] = collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => __('battery_documents.inspection.questions.'.$key)])
                ->all();
        }

        return $translated;
    }
}
