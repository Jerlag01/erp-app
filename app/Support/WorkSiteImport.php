<?php

namespace App\Support;

use App\Enums\BuildingType;
use App\Models\Country;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Imports COW sites from an Excel (.xlsx) or CSV file. The first row holds the column titles; the
 * user decides per column which field it fills (see targets()). Sites are matched on their COW code:
 * new ones are created, existing ones are only updated when asked (their building type is never
 * changed by an import, that keeps its own log).
 */
class WorkSiteImport
{
    public const MAX_ROWS = 10000;

    /**
     * Field => label of everything a column can be imported into.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        return [
            'cow_code' => __('erp.fields.cow_code'),
            'building_type' => __('erp.fields.building_type'),
            'street' => __('erp.fields.street_only'),
            'house_number' => __('erp.fields.house_number'),
            'street_with_number' => __('erp.work_site_import.targets.street_with_number'),
            'addition' => __('erp.fields.addition'),
            'postal_code' => __('erp.fields.postal_code'),
            'city' => __('erp.fields.city'),
            'postal_code_with_city' => __('erp.work_site_import.targets.postal_code_with_city'),
            'state' => __('erp.fields.state'),
            'country' => __('erp.fields.country'),
            'area' => __('erp.resources.work_site_area.singular'),
            'rooms' => __('erp.fields.rooms'),
        ];
    }

    /**
     * The column titles of the first row (empty ones get "Column N").
     *
     * @return list<string>
     */
    public function headers(string $path): array
    {
        foreach ($this->rows($path) as $row) {
            return array_map(fn (mixed $value, int $index): string => filled($value) ? trim((string) $value) : __('erp.work_site_import.column', ['number' => $index + 1]), $row, array_keys($row));
        }

        return [];
    }

    /**
     * A first guess of the field for a column title, e.g. "Zip code" → postal code.
     */
    public static function guess(string $header): ?string
    {
        $header = mb_strtolower(trim($header));

        return match (true) {
            (bool) preg_match('/cow/', $header) => 'cow_code',
            (bool) preg_match('/type|soort/', $header) => 'building_type',
            (bool) preg_match('/^(zip|post)|postcode|postal/', $header) => 'postal_code',
            (bool) preg_match('/code\s*&\s*city|postcode.*gemeente/', $header) => 'postal_code_with_city',
            (bool) preg_match('/^(city|stad|gemeente|ville|plaats)/', $header) => 'city',
            (bool) preg_match('/^(street|straat|rue|adres)/', $header) => 'street_with_number',
            (bool) preg_match('/huisnummer|^nr|number|numéro/', $header) => 'house_number',
            (bool) preg_match('/bus|toevoeging|addition|boîte/', $header) => 'addition',
            (bool) preg_match('/provin|state|regio/', $header) => 'state',
            (bool) preg_match('/land|country|pays/', $header) => 'country',
            (bool) preg_match('/area|zone|gebied/', $header) => 'area',
            (bool) preg_match('/lokal|room|local/', $header) => 'rooms',
            default => null,
        };
    }

    /**
     * @param  array<int, ?string>  $mapping  column index => field (null: not imported)
     * @return array{created: int, updated: int, unchanged: int, skipped: list<string>}
     */
    public function import(string $path, array $mapping, BuildingType $defaultType, bool $updateExisting): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => []];

        if (! in_array('cow_code', $mapping, true)) {
            $result['skipped'][] = __('erp.work_site_import.no_cow_column');

            return $result;
        }

        $line = 0;

        foreach ($this->rows($path) as $row) {
            if (++$line === 1) {
                continue;
            }

            if ($line > self::MAX_ROWS + 1) {
                $result['skipped'][] = __('erp.work_site_import.too_many', ['max' => self::MAX_ROWS]);
                break;
            }

            $values = $this->values($row, $mapping);

            if (blank($values['cow_code'] ?? null) && array_filter($values) === []) {
                continue;
            }

            $cow = strtoupper(trim((string) ($values['cow_code'] ?? '')));

            if (! preg_match(WorkSite::COW_CODE_PATTERN, $cow)) {
                $result['skipped'][] = __('erp.work_site_import.invalid_cow', ['line' => $line, 'value' => $cow ?: '—']);

                continue;
            }

            $attributes = $this->attributes($values);
            $site = WorkSite::query()->where('cow_code', $cow)->first();

            if ($site) {
                if (! $updateExisting) {
                    $result['unchanged']++;

                    continue;
                }

                $site->fill(array_filter($attributes, fn (mixed $value): bool => filled($value)));

                if ($site->isDirty()) {
                    $site->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }

                continue;
            }

            WorkSite::query()->create([
                'country_id' => Country::belgiumId(),
                ...array_filter($attributes, fn (mixed $value): bool => filled($value)),
                'cow_code' => $cow,
                'building_type' => self::buildingType($values['building_type'] ?? null) ?? $defaultType,
            ]);
            $result['created']++;
        }

        return $result;
    }

    /**
     * "RT.LDC" → LDC, "Colibri" → Colibri, "EC" → EC… or null when not recognised.
     */
    public static function buildingType(mixed $value): ?BuildingType
    {
        $value = mb_strtolower(trim((string) $value));

        return match (true) {
            $value === '' => null,
            (bool) preg_match('/colibri/', $value) => BuildingType::Colibri,
            (bool) preg_match('/contropos/', $value) => BuildingType::Contropos,
            (bool) preg_match('/atropos/', $value) => BuildingType::Atropos,
            (bool) preg_match('/(^|[^a-z])ldc([^a-z]|$)/', $value) => BuildingType::Ldc,
            (bool) preg_match('/(^|[^a-z])ec([^a-z]|$)/', $value) => BuildingType::Ec,
            (bool) preg_match('/atmk/', $value) => BuildingType::Atmk,
            (bool) preg_match('/decom|buiten dienst|hors service/', $value) => BuildingType::Decommissioned,
            (bool) preg_match('/onbekend|unknown|inconnu|^\?+$/', $value) => BuildingType::Unknown,
            default => BuildingType::tryFrom($value),
        };
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, ?string>  $mapping
     * @return array<string, string>
     */
    protected function values(array $row, array $mapping): array
    {
        $values = [];

        foreach ($mapping as $index => $field) {
            if ($field === null || ! array_key_exists($field, self::targets())) {
                continue;
            }

            $value = $row[$index] ?? null;
            $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : trim((string) $value);

            // Several columns into the same field (e.g. rooms) are joined.
            $values[$field] = filled($values[$field] ?? null) && $value !== '' ? $values[$field].', '.$value : ($values[$field] ?? $value);
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    protected function attributes(array $values): array
    {
        $attributes = [];
        $text = fn (?string $value, int $max): ?string => filled($value) ? mb_substr(trim((string) $value), 0, $max) : null;

        if (filled($values['street_with_number'] ?? null)) {
            // "Broekstraat, 72-74" or "Broekstraat 72": the street, then the house number.
            if (preg_match('/^(.*?)[,\s]+(\d[\w\/-]*)\s*$/u', trim($values['street_with_number']), $match)) {
                $attributes['street'] = $text($match[1], 150);
                $attributes['house_number'] = $text($match[2], 20);
            } else {
                $attributes['street'] = $text($values['street_with_number'], 150);
            }
        }

        if (filled($values['postal_code_with_city'] ?? null) && preg_match('/^(\d{4,5})\s+(.+)$/u', trim($values['postal_code_with_city']), $match)) {
            $attributes['postal_code'] = $match[1];
            $attributes['city'] = $text($match[2], 100);
        }

        foreach (['street' => 150, 'house_number' => 20, 'addition' => 20, 'postal_code' => 10, 'city' => 100, 'state' => 100] as $field => $max) {
            if (filled($values[$field] ?? null)) {
                $attributes[$field] = $text($values[$field], $max);
            }
        }

        if (filled($values['country'] ?? null)) {
            $attributes['country_id'] = $this->countryId($values['country']);
        }

        if (filled($values['area'] ?? null)) {
            $attributes['work_site_area_id'] = WorkSiteArea::query()->firstOrCreate(['name' => $text($values['area'], 100)])->id;
        }

        if (filled($values['rooms'] ?? null)) {
            $attributes['rooms'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\/|]+/', $values['rooms']) ?: []), 'filled')));
        }

        return $attributes;
    }

    protected function countryId(string $value): ?int
    {
        $value = mb_strtolower(trim($value));

        return Country::query()->get()->first(fn (Country $country): bool => mb_strtolower($country->code) === $value
            || in_array($value, array_map('mb_strtolower', array_values((array) $country->name)), true))?->id;
    }

    /**
     * The rows of the first sheet as plain arrays.
     *
     * @return \Generator<int, array<int, mixed>>
     */
    protected function rows(string $path): \Generator
    {
        $reader = $this->reader($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    yield $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    protected function reader(string $path): ReaderInterface
    {
        $handle = fopen($path, 'rb');
        $start = $handle ? (string) fread($handle, 4) : '';
        $handle && fclose($handle);

        // .xlsx files are zip archives; everything else is read as CSV (";" or ",").
        if (str_starts_with($start, "PK\x03\x04")) {
            return new XlsxReader;
        }

        $firstLine = (string) strtok((string) file_get_contents($path, length: 4096), "\n");
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return new CsvReader($options);
    }
}
