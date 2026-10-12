<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The full list of COW sites (database/data/work_sites.json, 1974 sites: address, province, area and
 * building type), so every installation starts with it. Only COW codes that do not exist yet are
 * added; the areas (1-VLA, 2-VLA, 3-BRU, 4-WAL, 5-WAL) are created when missing. Each new site starts
 * its building type log. Skipped while testing: the tests make their own sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $sites = json_decode((string) file_get_contents(database_path('data/work_sites.json')), true, flags: JSON_THROW_ON_ERROR);
        $existing = DB::table('work_sites')->pluck('cow_code')->map(fn (string $code): string => strtoupper($code))->flip();
        $belgium = DB::table('countries')->where('code', 'BE')->value('id');
        $now = now();
        $areas = [];

        foreach (array_unique(array_filter(array_column($sites, 'area'))) as $name) {
            $areas[$name] = DB::table('work_site_areas')->where('name', $name)->value('id')
                ?? DB::table('work_site_areas')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        $new = array_values(array_filter($sites, fn (array $site): bool => ! isset($existing[strtoupper($site['cow_code'])])));

        foreach (array_chunk($new, 500) as $chunk) {
            DB::table('work_sites')->insert(array_map(fn (array $site): array => [
                'cow_code' => strtoupper($site['cow_code']),
                'building_type' => $site['building_type'] ?? 'unknown',
                'country_id' => $belgium,
                'street' => $site['street'],
                'house_number' => $site['house_number'],
                'addition' => $site['addition'],
                'postal_code' => $site['postal_code'],
                'city' => $site['city'],
                'state' => $site['state'],
                'rooms' => filled($site['rooms'] ?? null) ? json_encode($site['rooms']) : null,
                'work_site_area_id' => filled($site['area'] ?? null) ? $areas[$site['area']] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        // The first building type starts the log of every new site.
        $ids = DB::table('work_sites')->whereIn('cow_code', array_map(fn (array $site): string => strtoupper($site['cow_code']), $new))->pluck('building_type', 'id');

        foreach ($ids->chunk(500) as $chunk) {
            DB::table('work_site_type_changes')->insert($chunk->map(fn (string $type, int $id): array => [
                'work_site_id' => $id,
                'from_type' => null,
                'to_type' => $type,
                'changed_on' => $now->toDateString(),
                'reason' => 'Import',
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
        }
    }

    /**
     * The sites are data that may have changed since: they are not removed on rollback.
     */
    public function down(): void {}
};
