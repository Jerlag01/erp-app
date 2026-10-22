<?php

namespace Database\Seeders;

use App\Enums\KbFormat;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ERP app manual in the knowledge base: one category with an article per module, in Dutch, French
 * and English (texts in database/seeders/erp-manual). Safe to run again: the category and articles
 * are found by their Dutch name and title and updated, nothing else is touched.
 *
 *     php artisan db:seed --class=ErpManualSeeder --force
 */
class ErpManualSeeder extends Seeder
{
    public const LOCALES = ['nl', 'fr', 'en'];

    public function run(): void
    {
        $texts = collect(self::LOCALES)->mapWithKeys(fn (string $locale): array => [$locale => require __DIR__."/erp-manual/{$locale}.php"]);
        $keys = array_keys($texts['nl']['articles']);

        foreach (self::LOCALES as $locale) {
            if (array_keys($texts[$locale]['articles']) !== $keys) {
                throw new RuntimeException("The {$locale} manual does not have the same articles as the Dutch one.");
            }
        }

        $translate = fn (string $field, ?string $article = null): array => collect(self::LOCALES)
            ->mapWithKeys(fn (string $locale): array => [$locale => $article === null ? $texts[$locale]['category'][$field] : $texts[$locale]['articles'][$article][$field]])
            ->all();

        DB::transaction(function () use ($translate, $keys): void {
            $category = KbCategory::query()->where('name->nl', $translate('name')['nl'])->first() ?? new KbCategory;
            $category->fill([
                'name' => $translate('name'),
                'description' => $translate('description'),
                'is_visible' => true,
                'sort_order' => 0,
            ])->save();

            foreach ($keys as $position => $key) {
                $title = $translate('title', $key);
                $article = $category->articles()->where('title->nl', $title['nl'])->first() ?? new KbArticle(['kb_category_id' => $category->id]);

                $article->fill([
                    'title' => $title,
                    'format' => KbFormat::Markdown,
                    'body' => $translate('body', $key),
                    'is_published' => true,
                    'sort_order' => ($position + 1) * 10,
                ])->save();
            }
        });

        $this->command?->info(count($keys).' articles in "'.$translate('name')['nl'].'".');
    }
}
