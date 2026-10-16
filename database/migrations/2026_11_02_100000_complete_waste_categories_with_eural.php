<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The complete European List of Waste (Eural) as waste categories: every subchapter (e.g. "16 06
 * batterijen en accu's") is a category and every waste code a subcategory, 842 in all.
 *
 * Categories that already exist with one of these codes keep their name and their entries; they move
 * under the subchapter of their code. The old groups that become empty are removed (or switched off when
 * entries were booked on them). Battery codes (16 06 and 20 01 33* / 20 01 34) are battery waste.
 */
return new class extends Migration
{
    public function up(): void
    {
        $data = json_decode((string) file_get_contents(database_path('data/eural_nl.json')), true);
        $now = now();
        $normalize = fn (?string $code): string => preg_replace('/\s+/', '', (string) $code) ?? '';
        $label = fn (string $text): string => mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
        $batterySubchapters = ['16 06'];
        $batteryCodes = ['200133*', '200134'];

        $existing = DB::table('waste_categories')->whereNotNull('waste_code')->get()->keyBy(fn (object $category): string => $normalize($category->waste_code));
        $oldGroups = DB::table('waste_categories')->whereNull('parent_id')->whereNull('waste_code')->pluck('id')->all();

        $subchapterIds = [];

        foreach ($data['subchapters'] as $index => [$code, $name]) {
            $id = $existing->get($normalize($code))?->id;
            $isBatteries = in_array($code, $batterySubchapters, true);

            if ($id === null) {
                $id = DB::table('waste_categories')->insertGetId([
                    'parent_id' => null, 'name' => $label($name), 'waste_code' => $code, 'is_batteries' => $isBatteries,
                    'is_active' => true, 'sort_order' => 100 + $index, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                DB::table('waste_categories')->where('id', $id)->update(['parent_id' => null, 'sort_order' => 100 + $index, 'is_batteries' => DB::raw($isBatteries ? 'true' : 'is_batteries'), 'updated_at' => $now]);
            }

            $subchapterIds[$code] = $id;
        }

        foreach ($data['codes'] as $index => [$code, $name]) {
            $parentId = $subchapterIds[substr($code, 0, 5)];
            $isBatteries = in_array(substr($code, 0, 5), $batterySubchapters, true) || in_array($normalize($code), $batteryCodes, true);
            $found = $existing->get($normalize($code));

            if ($found !== null) {
                // An existing category with this code keeps its name and entries, under its subchapter.
                if (DB::table('waste_categories')->where('parent_id', $found->id)->doesntExist()) {
                    DB::table('waste_categories')->where('id', $found->id)->update([
                        'parent_id' => $parentId, 'waste_code' => $code, 'sort_order' => $index,
                        'is_batteries' => $isBatteries || $found->is_batteries, 'updated_at' => $now,
                    ]);
                }

                continue;
            }

            DB::table('waste_categories')->insert([
                'parent_id' => $parentId, 'name' => $label($name), 'waste_code' => $code, 'is_batteries' => $isBatteries,
                'is_active' => true, 'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // The former groups without a code ("Batterijen", "Kabels", …) are empty now.
        foreach ($oldGroups as $id) {
            if (DB::table('waste_categories')->where('parent_id', $id)->exists()) {
                continue;
            }

            if (DB::table('waste_entries')->where('waste_category_id', $id)->exists()) {
                DB::table('waste_categories')->where('id', $id)->update(['is_active' => false, 'updated_at' => $now]);
            } else {
                DB::table('waste_categories')->where('id', $id)->delete();
            }
        }
    }

    public function down(): void
    {
        // The list stays: categories may hold entries by now.
    }
};
