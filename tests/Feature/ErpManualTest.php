<?php

namespace Tests\Feature;

use App\Filament\App\Pages\KnowledgeBase;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Database\Seeders\ErpManualSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErpManualTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_manual_is_seeded_in_three_languages_and_can_be_run_again(): void
    {
        $this->seed(ErpManualSeeder::class);
        $this->seed(ErpManualSeeder::class);

        $category = KbCategory::query()->sole();
        $this->assertSame(['nl' => 'ERP-app handleiding', 'fr' => 'Manuel de l\'application ERP', 'en' => 'ERP app manual'], $category->name);

        $articles = $category->articles;
        $this->assertGreaterThanOrEqual(30, $articles->count());
        $this->assertSame($articles->count(), KbArticle::query()->count(), 'Running it again adds nothing.');

        foreach ($articles as $article) {
            foreach (ErpManualSeeder::LOCALES as $locale) {
                $this->assertNotEmpty($article->title[$locale] ?? null, "Title {$locale} of article {$article->id}");
                $this->assertStringContainsString('<h2>', $article->renderedBody($locale)->toHtml(), "Body {$locale} of {$article->title['nl']}");
            }
        }

        // Every employee reads it in their own language.
        $gpw = $articles->firstWhere('title.nl', 'GPW-aanvragen');
        $this->actingAs(User::factory()->create(['locale' => 'fr']))
            ->get(KnowledgeBase::articleUrl($gpw))
            ->assertOk()
            ->assertSee('Demandes de GPW')
            ->assertSee('Demander un GPW');
        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->get('/knowledge-base')
            ->assertOk()
            ->assertSee('ERP app manual')
            ->assertSee('Getting started: signing in, menu and language');
    }
}
