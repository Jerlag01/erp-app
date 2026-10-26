<?php

namespace App\Support;

/**
 * Cable section as an indication, the way Schneider's cable calculator (and Ecodial) works, after
 * IEC 60364-5-52: the smallest section that meets
 *
 *  - the current: Iz = Iz0 (table B.52.2–B.52.5, B.52.10–B.52.11) × k1 (temperature) × k2 (grouping) ≥ In
 *    of the protective device (the next standard rating above Ib when none is given);
 *  - the voltage drop: ΔU = b · Ib · L · (ρ/S · cos φ + λ · sin φ), b = √3 three-phase and 2 single-phase
 *    or DC, ρ at operating temperature (Cu 0.0225, Al 0.036 Ω·mm²/m) and λ = 0.08 mΩ/m (AC);
 *  - optionally the short-circuit: S ≥ Ik · √t / k (k after insulation and metal).
 *
 * Aluminium uses 78 % of the copper values, from 16 mm². Parallel conductors share the current.
 * The result is an indication, not a substitute for a full study.
 */
class CableCalculator
{
    public const SYSTEMS = ['ac3', 'ac1', 'dc'];

    /** Usual voltage per system. */
    public const VOLTAGES = ['ac3' => 400, 'ac1' => 230, 'dc' => 48];

    public const MATERIALS = ['cu', 'al'];

    public const INSULATIONS = ['pvc', 'xlpe'];

    public const METHODS = ['A1', 'A2', 'B1', 'B2', 'C', 'D1', 'E'];

    public const SECTIONS = [1.5, 2.5, 4, 6, 10, 16, 25, 35, 50, 70, 95, 120, 150, 185, 240, 300];

    /** Aluminium from 16 mm². */
    public const ALUMINIUM_FROM = 16;

    public const ALUMINIUM_FACTOR = 0.78;

    /** Standard ratings of protective devices (A). */
    public const RATINGS = [6, 10, 13, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125, 160, 200, 250, 315, 400, 500, 630, 800, 1000, 1250];

    /** Resistivity at operating temperature (Ω·mm²/m) and reactance (Ω/m). */
    public const RESISTIVITY = ['cu' => 0.0225, 'al' => 0.036];

    public const REACTANCE = 0.00008;

    /** k for the short-circuit check (IEC 60364-4-43). */
    public const SHORT_CIRCUIT_K = ['cu' => ['pvc' => 115, 'xlpe' => 143], 'al' => ['pvc' => 76, 'xlpe' => 94]];

    /**
     * Iz0 of copper per insulation, loaded conductors and method, per section of SECTIONS (A).
     *
     * @var array<string, array<int, array<string, list<float>>>>
     */
    public const CAPACITY = [
        'pvc' => [
            2 => [
                'A1' => [14.5, 19.5, 26, 34, 46, 61, 80, 99, 119, 151, 182, 210, 240, 273, 321, 367],
                'A2' => [14, 18.5, 25, 32, 43, 57, 75, 92, 110, 139, 167, 192, 219, 248, 291, 334],
                'B1' => [17.5, 24, 32, 41, 57, 76, 101, 125, 151, 192, 232, 269, 300, 341, 400, 458],
                'B2' => [16.5, 23, 30, 38, 52, 69, 90, 111, 133, 168, 201, 232, 258, 294, 344, 394],
                'C' => [19.5, 27, 36, 46, 63, 85, 112, 138, 168, 213, 258, 299, 344, 392, 461, 530],
                'D1' => [22, 29, 38, 47, 63, 81, 104, 125, 148, 183, 216, 246, 278, 312, 361, 408],
                'E' => [22, 30, 40, 51, 70, 94, 119, 148, 180, 232, 282, 328, 379, 434, 514, 593],
            ],
            3 => [
                'A1' => [13.5, 18, 24, 31, 42, 56, 73, 89, 108, 136, 164, 188, 216, 245, 286, 328],
                'A2' => [13, 17.5, 23, 29, 39, 52, 68, 83, 99, 125, 150, 172, 196, 223, 261, 298],
                'B1' => [15.5, 21, 28, 36, 50, 68, 89, 110, 134, 171, 207, 239, 262, 296, 346, 394],
                'B2' => [15, 20, 27, 34, 46, 62, 80, 99, 118, 149, 179, 206, 225, 255, 297, 339],
                'C' => [17.5, 24, 32, 41, 57, 76, 96, 119, 144, 184, 223, 259, 299, 341, 403, 464],
                'D1' => [18, 24, 31, 39, 52, 67, 86, 103, 122, 151, 179, 203, 230, 258, 297, 336],
                'E' => [18.5, 25, 34, 43, 60, 80, 101, 126, 153, 196, 238, 276, 319, 364, 430, 497],
            ],
        ],
        'xlpe' => [
            2 => [
                'A1' => [19, 26, 35, 45, 61, 81, 106, 131, 158, 200, 241, 278, 318, 362, 424, 486],
                'A2' => [18.5, 25, 33, 42, 57, 76, 99, 121, 145, 183, 220, 253, 290, 329, 386, 442],
                'B1' => [23, 31, 42, 54, 75, 100, 133, 164, 198, 253, 306, 354, 393, 449, 528, 603],
                'B2' => [22, 30, 40, 51, 69, 91, 119, 146, 175, 221, 265, 305, 334, 384, 459, 532],
                'C' => [24, 33, 45, 58, 80, 107, 138, 171, 209, 269, 328, 382, 441, 506, 599, 693],
                'D1' => [25, 33, 43, 53, 71, 91, 116, 139, 164, 203, 239, 271, 306, 343, 395, 446],
                'E' => [26, 36, 49, 63, 86, 115, 149, 185, 225, 289, 352, 410, 473, 542, 641, 741],
            ],
            3 => [
                'A1' => [17, 23, 31, 40, 54, 73, 95, 117, 141, 179, 216, 249, 285, 324, 380, 435],
                'A2' => [16.5, 22, 30, 38, 51, 68, 89, 109, 130, 164, 197, 227, 259, 295, 346, 396],
                'B1' => [20, 28, 37, 48, 66, 88, 117, 144, 175, 222, 269, 312, 342, 384, 450, 514],
                'B2' => [19.5, 26, 35, 44, 60, 80, 105, 128, 154, 194, 233, 268, 300, 340, 398, 455],
                'C' => [22, 30, 40, 52, 71, 96, 119, 147, 179, 229, 278, 322, 371, 424, 500, 576],
                'D1' => [21, 28, 36, 44, 58, 75, 96, 115, 135, 167, 197, 223, 251, 281, 324, 365],
                'E' => [23, 32, 42, 54, 75, 100, 127, 158, 192, 246, 298, 346, 399, 456, 538, 621],
            ],
        ],
    ];

    /**
     * k1: ambient air temperature (B.52.14, reference 30 °C) and ground temperature for D1
     * (B.52.15, reference 20 °C), per insulation, by temperature (°C).
     *
     * @var array<string, array<string, array<int, float>>>
     */
    public const TEMPERATURE = [
        'air' => [
            'pvc' => [10 => 1.22, 15 => 1.17, 20 => 1.12, 25 => 1.06, 30 => 1.0, 35 => 0.94, 40 => 0.87, 45 => 0.79, 50 => 0.71, 55 => 0.61, 60 => 0.50],
            'xlpe' => [10 => 1.15, 15 => 1.12, 20 => 1.08, 25 => 1.04, 30 => 1.0, 35 => 0.96, 40 => 0.91, 45 => 0.87, 50 => 0.82, 55 => 0.76, 60 => 0.71, 65 => 0.65, 70 => 0.58],
        ],
        'ground' => [
            'pvc' => [10 => 1.10, 15 => 1.05, 20 => 1.0, 25 => 0.95, 30 => 0.89, 35 => 0.84, 40 => 0.77, 45 => 0.71, 50 => 0.63, 55 => 0.55, 60 => 0.45],
            'xlpe' => [10 => 1.07, 15 => 1.04, 20 => 1.0, 25 => 0.96, 30 => 0.93, 35 => 0.89, 40 => 0.85, 45 => 0.80, 50 => 0.76, 55 => 0.71, 60 => 0.65, 65 => 0.60, 70 => 0.53],
        ],
    ];

    /**
     * k2: grouping of circuits (B.52.17 bunched / single layer on a wall / perforated tray, and
     * B.52.18 buried), by number of circuits.
     *
     * @var array<string, array<int, float>>
     */
    public const GROUPING = [
        'bunched' => [1 => 1.0, 2 => 0.80, 3 => 0.70, 4 => 0.65, 5 => 0.60, 6 => 0.57, 7 => 0.54, 8 => 0.52, 9 => 0.50, 12 => 0.45, 16 => 0.41, 20 => 0.38],
        'wall' => [1 => 1.0, 2 => 0.85, 3 => 0.79, 4 => 0.75, 5 => 0.73, 6 => 0.72, 7 => 0.72, 8 => 0.71, 9 => 0.70],
        'tray' => [1 => 1.0, 2 => 0.88, 3 => 0.82, 4 => 0.77, 5 => 0.75, 6 => 0.73, 7 => 0.73, 8 => 0.72, 9 => 0.72],
        'buried' => [1 => 1.0, 2 => 0.75, 3 => 0.65, 4 => 0.60, 5 => 0.55, 6 => 0.50, 7 => 0.45, 8 => 0.43, 9 => 0.41, 12 => 0.36, 16 => 0.32, 20 => 0.29],
    ];

    /** Which grouping table a method uses. */
    public const METHOD_GROUPING = ['A1' => 'bunched', 'A2' => 'bunched', 'B1' => 'bunched', 'B2' => 'bunched', 'C' => 'wall', 'D1' => 'buried', 'E' => 'tray'];

    /**
     * The default values of a new calculation.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'system' => 'ac3',
            'voltage' => 400,
            'load_type' => 'current',
            'load_current' => null,
            'load_power' => null,
            'power_factor' => 0.85,
            'length' => null,
            'material' => 'cu',
            'insulation' => 'xlpe',
            'installation_method' => 'C',
            'ambient_temperature' => 30,
            'grouped_circuits' => 1,
            'parallel_conductors' => 1,
            'max_voltage_drop' => 3,
            'protective_device' => null,
            'short_circuit_ka' => null,
            'short_circuit_time' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     ok: bool,
     *     errors: list<string>,
     *     ib: ?float, in: ?float, loaded: int, k1: ?float, k2: ?float, parallel: int,
     *     section: ?float, iz: ?float, iz0: ?float, drop_v: ?float, drop_pct: ?float,
     *     by_current: ?float, by_drop: ?float, by_short_circuit: ?float, s_min_short_circuit: ?float,
     *     governing: ?string, rows: list<array{section: float, iz: float, drop_pct: float, current_ok: bool, drop_ok: bool, short_circuit_ok: bool}>
     * }
     */
    public static function calculate(array $input): array
    {
        $input = [...self::defaults(), ...array_filter($input, fn (mixed $value): bool => $value !== null && $value !== '')];
        $system = in_array($input['system'], self::SYSTEMS, true) ? $input['system'] : 'ac3';
        $material = in_array($input['material'], self::MATERIALS, true) ? $input['material'] : 'cu';
        $insulation = in_array($input['insulation'], self::INSULATIONS, true) ? $input['insulation'] : 'xlpe';
        $method = in_array($input['installation_method'], self::METHODS, true) ? $input['installation_method'] : 'C';
        $voltage = (float) $input['voltage'];
        $cosPhi = $system === 'dc' ? 1.0 : min(1.0, max(0.1, (float) $input['power_factor']));
        $length = (float) $input['length'];
        $parallel = max(1, (int) $input['parallel_conductors']);
        $loaded = $system === 'ac3' ? 3 : 2;
        $errors = [];

        $ib = match (true) {
            $input['load_type'] === 'power' && (float) $input['load_power'] > 0 && $voltage > 0 => match ($system) {
                'ac3' => (float) $input['load_power'] * 1000 / (sqrt(3) * $voltage * $cosPhi),
                'ac1' => (float) $input['load_power'] * 1000 / ($voltage * $cosPhi),
                default => (float) $input['load_power'] * 1000 / $voltage,
            },
            $input['load_type'] !== 'power' && (float) $input['load_current'] > 0 => (float) $input['load_current'],
            default => null,
        };

        if ($ib === null) {
            $errors[] = 'load';
        }

        if ($voltage <= 0) {
            $errors[] = 'voltage';
        }

        if ($length <= 0) {
            $errors[] = 'length';
        }

        $in = match (true) {
            (float) $input['protective_device'] > 0 => (float) $input['protective_device'],
            $ib !== null => (float) (collect(self::RATINGS)->first(fn (int $rating): bool => $rating >= $ib) ?? ceil($ib)),
            default => null,
        };

        if ($in !== null && $ib !== null && $in < $ib) {
            $errors[] = 'device_too_small';
        }

        $k1 = self::lookupUp(self::TEMPERATURE[$method === 'D1' ? 'ground' : 'air'][$insulation], (float) $input['ambient_temperature']);
        $k2 = self::lookupDown(self::GROUPING[self::METHOD_GROUPING[$method]], max(1, (int) $input['grouped_circuits']));

        if ($k1 === null) {
            $errors[] = 'temperature';
        }

        $empty = ['ib' => $ib, 'in' => $in, 'loaded' => $loaded, 'k1' => $k1, 'k2' => $k2, 'parallel' => $parallel, 'section' => null, 'iz' => null, 'iz0' => null, 'drop_v' => null, 'drop_pct' => null, 'by_current' => null, 'by_drop' => null, 'by_short_circuit' => null, 's_min_short_circuit' => null, 'governing' => null, 'rows' => []];

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, ...$empty];
        }

        $ibPer = $ib / $parallel;
        $inPer = $in / $parallel;
        $sinPhi = $system === 'dc' ? 0.0 : sqrt(1 - $cosPhi ** 2);
        $reactance = $system === 'dc' ? 0.0 : self::REACTANCE;
        $factor = $system === 'ac3' ? sqrt(3) : 2.0;
        $maxDrop = (float) $input['max_voltage_drop'];
        $ik = (float) $input['short_circuit_ka'] * 1000;
        $time = (float) $input['short_circuit_time'];
        $sMinShortCircuit = $ik > 0 && $time > 0 ? ($ik / $parallel) * sqrt($time) / self::SHORT_CIRCUIT_K[$material][$insulation] : null;
        $rows = [];

        foreach (self::SECTIONS as $index => $section) {
            if ($material === 'al' && $section < self::ALUMINIUM_FROM) {
                continue;
            }

            $iz0 = self::CAPACITY[$insulation][$loaded][$method][$index] * ($material === 'al' ? self::ALUMINIUM_FACTOR : 1);
            $iz = $iz0 * $k1 * $k2;
            $dropV = $factor * $ibPer * $length * (self::RESISTIVITY[$material] / $section * $cosPhi + $reactance * $sinPhi);
            $dropPct = 100 * $dropV / $voltage;

            $rows[] = [
                'section' => (float) $section,
                'iz0' => round($iz0, 1),
                'iz' => round($iz, 1),
                'drop_v' => round($dropV, 2),
                'drop_pct' => round($dropPct, 2),
                'current_ok' => $iz >= $inPer,
                'drop_ok' => $maxDrop <= 0 || $dropPct <= $maxDrop,
                'short_circuit_ok' => $sMinShortCircuit === null || $section >= $sMinShortCircuit,
            ];
        }

        $first = fn (string $check): ?float => collect($rows)->first(fn (array $row): bool => $row[$check])['section'] ?? null;
        $chosen = collect($rows)->first(fn (array $row): bool => $row['current_ok'] && $row['drop_ok'] && $row['short_circuit_ok']);
        $byCurrent = $first('current_ok');
        $byDrop = $first('drop_ok');
        $byShortCircuit = $first('short_circuit_ok');

        // Which criterion sets the section: the one that needs the largest.
        $governing = $chosen === null ? null : collect(['current' => $byCurrent, 'voltage_drop' => $byDrop, 'short_circuit' => $sMinShortCircuit === null ? null : $byShortCircuit])
            ->filter()
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'ok' => $chosen !== null,
            'errors' => $chosen === null ? ['no_section'] : [],
            ...$empty,
            'section' => $chosen['section'] ?? null,
            'iz' => isset($chosen) ? round($chosen['iz'] * $parallel, 1) : null,
            'iz0' => $chosen['iz0'] ?? null,
            'drop_v' => $chosen['drop_v'] ?? null,
            'drop_pct' => $chosen['drop_pct'] ?? null,
            'by_current' => $byCurrent,
            'by_drop' => $byDrop,
            'by_short_circuit' => $sMinShortCircuit === null ? null : $byShortCircuit,
            's_min_short_circuit' => $sMinShortCircuit === null ? null : round($sMinShortCircuit, 1),
            'governing' => $governing,
            'ib' => round($ib, 2),
            'in' => $in,
            'rows' => $rows,
        ];
    }

    /**
     * "3 × 6 mm² Cu XLPE" or "2 × (3 × 95 mm²) …" with parallel conductors; the number of loaded
     * conductors per system (three-phase 3, single-phase and DC 2).
     */
    public static function label(float $section, string $material, string $insulation, int $loaded, int $parallel = 1): string
    {
        $core = $loaded.' × '.self::number($section).' mm²';

        return ($parallel > 1 ? $parallel.' × ('.$core.')' : $core).' '.ucfirst($material).' '.strtoupper($insulation);
    }

    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }

    /**
     * The factor at the first step at or above the value (the safe side); null beyond the table.
     *
     * @param  array<int, float>  $table
     */
    protected static function lookupUp(array $table, float $value): ?float
    {
        foreach ($table as $step => $factor) {
            if ($value <= $step) {
                return $factor;
            }
        }

        return null;
    }

    /**
     * The factor at the last step at or below the value (the table's last step for more).
     *
     * @param  array<int, float>  $table
     */
    protected static function lookupDown(array $table, int $value): float
    {
        $found = reset($table);

        foreach ($table as $step => $factor) {
            if ($step <= $value) {
                $found = $factor;
            }
        }

        return $found;
    }
}
