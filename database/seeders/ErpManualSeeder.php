<?php

namespace Database\Seeders;

use App\Enums\KbFormat;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ERP app manual in the knowledge base: a main category with a subcategory per module, each with
 * its article, in Dutch, French and English (texts in database/seeders/erp-manual). Safe to run again:
 * the categories and articles are found by their Dutch name and title and updated (articles of an
 * earlier run straight in the main category move to their subcategory); nothing else is touched.
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
            $category = KbCategory::query()->whereNull('parent_id')->where('name->nl', $translate('name')['nl'])->first() ?? new KbCategory;
            $category->fill([
                'parent_id' => null,
                'name' => $translate('name'),
                'description' => $translate('description'),
                'is_visible' => true,
                'sort_order' => 0,
            ])->save();

            // Each module in its own subcategory, named after it, with its article.
            $categoryIds = fn (): array => [$category->id, ...$category->children()->pluck('id')->all()];

            foreach ($keys as $position => $key) {
                $title = $translate('title', $key);
                $subcategory = $category->children()->where('name->nl', $title['nl'])->first() ?? new KbCategory(['parent_id' => $category->id]);
                $subcategory->fill([
                    'parent_id' => $category->id,
                    // Only shown to those with access to the module (see ManualAccess).
                    'module_key' => $key,
                    'name' => $title,
                    'is_visible' => true,
                    'sort_order' => ($position + 1) * 10,
                ])->save();

                $article = KbArticle::query()->whereIn('kb_category_id', $categoryIds())->where('title->nl', $title['nl'])->first() ?? new KbArticle;

                $article->fill([
                    'kb_category_id' => $subcategory->id,
                    'title' => $title,
                    'format' => KbFormat::Markdown,
                    'body' => $translate('body', $key),
                    'is_published' => true,
                    'sort_order' => 10,
                ])->save();
            }
        });

        $this->command?->info(count($keys).' modules (subcategories with their article) in "'.$translate('name')['nl'].'".');
    }
}
