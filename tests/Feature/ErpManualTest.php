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
        // An earlier run put the articles straight in the main category: they move to their subcategory.
        $old = KbCategory::query()->create(['name' => ['nl' => 'ERP-app handleiding', 'fr' => 'x', 'en' => 'x'], 'is_visible' => true]);
        $moved = KbArticle::factory()->for($old, 'category')->create(['title' => ['nl' => 'GPW-aanvragen', 'fr' => 'x', 'en' => 'x']]);

        $this->seed(ErpManualSeeder::class);
        $this->seed(ErpManualSeeder::class);

        $category = KbCategory::query()->whereNull('parent_id')->sole();
        $this->assertSame($old->id, $category->id);
        $this->assertSame(['nl' => 'ERP-app handleiding', 'fr' => 'Manuel de l\'application ERP', 'en' => 'ERP app manual'], $category->name);
        $this->assertSame(0, $category->articles()->count());

        // One subcategory per module, named after it, with its article.
        $subcategories = $category->children()->with('articles')->get();
        $this->assertGreaterThanOrEqual(30, $subcategories->count());
        $this->assertTrue($subcategories->every(fn (KbCategory $sub): bool => $sub->articles->count() === 1 && $sub->name === $sub->articles->first()->title));
        $articles = $subcategories->flatMap->articles;
        $this->assertSame($articles->count(), KbArticle::query()->count(), 'Running it again adds nothing.');
        $this->assertSame($moved->id, $articles->firstWhere('title.nl', 'GPW-aanvragen')->id);

        foreach ($articles as $article) {
            foreach (ErpManualSeeder::LOCALES as $locale) {
                $this->assertNotEmpty($article->title[$locale] ?? null, "Title {$locale} of article {$article->id}");
                $this->assertStringContainsString('<h2>', $article->renderedBody($locale)->toHtml(), "Body {$locale} of {$article->title['nl']}");
            }
        }

        // Every employee reads it in their own language (the GPW article with the GPW permission).
        $gpw = $articles->firstWhere('title.nl', 'GPW-aanvragen');
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'gpw']])->inTeam()->create(['locale' => 'fr']))
            ->get(KnowledgeBase::articleUrl($gpw))
            ->assertOk()
            ->assertSee('Demandes de GPW')
            ->assertSee('Demander un GPW');
        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->get('/knowledge-base')
            ->assertOk()
            ->assertSee('ERP app manual')
            ->assertSee('Getting started: signing in, menu and language');

        // A module's subcategory lists its article; the article shows the path.
        $planning = $subcategories->firstWhere('name.nl', 'Planning en opleidingen');
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'gpw']])->inTeam()->create(['locale' => 'nl']))
            ->get('/knowledge-base?category='.$planning->id)
            ->assertOk()
            ->assertSee('Planning en opleidingen')
            ->assertSee('ERP-app handleiding');
        $this->get(KnowledgeBase::articleUrl($gpw))->assertSee('ERP-app handleiding › GPW-aanvragen');
    }

    #[Test]
    public function a_modules_article_is_only_shown_to_those_with_access_to_that_module(): void
    {
        $this->seed(ErpManualSeeder::class);
        $fleet = KbArticle::query()->where('title->nl', 'Wagenpark')->sole();
        $start = KbArticle::query()->where('title->nl', 'like', 'Aan de slag%')->sole();
        $finance = KbArticle::query()->where('title->nl', 'like', 'Financieel%')->sole();

        // An employee without fleet or finance rights: not those, but the general articles.
        $employee = User::factory()->inTeam()->create(['locale' => 'nl']);
        $this->actingAs($employee);
        $this->get('/knowledge-base')->assertOk()->assertSee($start->title['nl'])->assertDontSee('Wagenpark')->assertDontSee('Financieel');
        $this->get(KnowledgeBase::articleUrl($fleet))->assertNotFound();
        $this->get('/knowledge-base?search=voertuig')->assertDontSee('Wagenpark');

        // With the fleet permission they read it.
        $driver = User::factory()->withPermissions(['vehicles' => ['view']])->inTeam()->create(['locale' => 'nl']);
        $this->actingAs($driver);
        $this->get(KnowledgeBase::articleUrl($fleet))->assertOk();
        $this->get(KnowledgeBase::articleUrl($finance))->assertNotFound();

        // Administrators and knowledge base editors read everything.
        $this->actingAs(User::factory()->admin()->create())->get(KnowledgeBase::articleUrl($finance))->assertOk();
        $this->actingAs(User::factory()->withPermissions(['knowledge_base' => ['update']])->inTeam()->create())->get(KnowledgeBase::articleUrl($finance))->assertOk();
    }
}
