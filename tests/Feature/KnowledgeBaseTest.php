<?php

namespace Tests\Feature;

use App\Enums\KbFormat;
use App\Filament\App\Pages\KnowledgeBase;
use App\Filament\Resources\KbArticles\Pages\CreateKbArticle;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function editor(): User
    {
        return User::factory()->withPermissions(['knowledge_base' => ['create', 'update', 'delete']])->inTeam()->create();
    }

    #[Test]
    public function every_employee_reads_published_articles_in_their_language(): void
    {
        $article = KbArticle::factory()->create([
            'title' => ['nl' => 'Verlof aanvragen', 'fr' => 'Demander un congé', 'en' => 'Request leave'],
            'body' => ['nl' => 'Klik op **Aanvragen**.', 'fr' => 'Cliquez sur **Demander**.', 'en' => 'Click **Request**.'],
        ]);
        $guest = User::factory()->create(['locale' => 'fr']); // no team, no permissions

        $this->actingAs($guest)->get('/knowledge-base')->assertOk()->assertSee('Demander un congé');
        $this->actingAs($guest)->get('/knowledge-base?article='.$article->id)->assertOk()->assertSee('<strong>Demander</strong>', escape: false);
        $this->actingAs($guest)->get('/knowledge-base-articles')->assertForbidden();
    }

    #[Test]
    public function drafts_and_hidden_categories_are_only_shown_to_editors(): void
    {
        $draft = KbArticle::factory()->draft()->create(['title' => ['nl' => 'Concepttekst']]);
        $hidden = KbArticle::factory()->for(KbCategory::factory()->hidden(), 'category')->create(['title' => ['nl' => 'Geheime procedure']]);
        $employee = User::factory()->inTeam()->create();

        $this->actingAs($employee)->get('/knowledge-base')->assertOk()->assertDontSee('Concepttekst')->assertDontSee('Geheime procedure');
        $this->actingAs($employee)->get('/knowledge-base?article='.$draft->id)->assertNotFound();
        $this->actingAs($employee)->get('/knowledge-base?article='.$hidden->id)->assertNotFound();

        $this->actingAs($this->editor())->get('/knowledge-base')->assertOk()->assertSee('Concepttekst')->assertSee('Geheime procedure');
    }

    #[Test]
    public function search_looks_in_title_and_text(): void
    {
        KbArticle::factory()->create(['title' => ['nl' => 'Wagen tanken'], 'body' => ['nl' => 'Gebruik de **tankkaart** van de firma.']]);
        KbArticle::factory()->create(['title' => ['nl' => 'Verlof']]);
        $this->actingAs(User::factory()->inTeam()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(KnowledgeBase::class)
            ->set('search', 'tankkaart')
            ->assertSee('Wagen tanken')
            ->assertDontSee('Verlof');
    }

    #[Test]
    public function editors_write_articles_with_downloads_that_only_signed_in_users_can_open(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $category = KbCategory::factory()->create();
        $editor = $this->editor();
        $this->actingAs($editor);
        Filament::setCurrentPanel('app');

        Livewire::test(CreateKbArticle::class)
            ->fillForm([
                'kb_category_id' => $category->id,
                'title' => ['nl' => 'Werfreglement', 'fr' => 'Règlement de chantier', 'en' => 'Site rules'],
                'format' => KbFormat::Markdown->value,
                'body' => ['nl' => 'Zie de bijlage.'],
                'attachments' => [UploadedFile::fake()->create('reglement.pdf', 50, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = KbArticle::sole();
        $this->assertTrue($article->editor->is($editor));
        $this->assertSame('reglement.pdf', $article->downloads()[0]['name']);

        $url = route('kb.download', ['article' => $article, 'index' => 0]);
        auth()->logout();
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertDownload('reglement.pdf');
    }

    #[Test]
    public function pictures_can_be_placed_anywhere_in_the_text_and_beside_it_in_columns(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('kb/images/schema.png', UploadedFile::fake()->image('schema.png')->getContent());
        // The editor stores HTML; pictures carry the stored path in data-id.
        $image = '<img data-id="kb/images/schema.png" alt="Schema" width="320">';

        $article = KbArticle::factory()->create([
            'format' => KbFormat::RichText,
            'body' => ['nl' => '<p>Eerst de inleiding.</p><p>'.$image.'</p><p>Daarna meer uitleg.</p>'
                .'<div class="grid-layout" data-cols="2" data-from-breakpoint="md">'
                .'<div class="grid-layout-col" data-col-span="1"><p>'.$image.'</p></div>'
                .'<div class="grid-layout-col" data-col-span="1"><p>Tekst naast de foto.</p></div></div>'],
        ]);

        $html = $article->renderedBody('nl')->toHtml();
        $url = Storage::disk('public')->url('kb/images/schema.png');

        $this->assertSame(2, substr_count($html, $url), 'Both pictures point to the stored file.');
        $this->assertStringContainsString('width: 320px', $html);
        $this->assertStringContainsString('class="grid-layout"', $html);
        $this->assertTrue(strpos($html, 'Eerst de inleiding') < strpos($html, $url) && strpos($html, $url) < strpos($html, 'Daarna meer uitleg'), 'The picture stays where it was placed.');

        $this->actingAs(User::factory()->inTeam()->create())->get('/knowledge-base?article='.$article->id)->assertOk()->assertSee($url, escape: false);
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        KbArticle::factory()->create();
        $this->setModules(['knowledge_base' => false]);
        $editor = $this->editor();

        $this->actingAs($editor)->get('/knowledge-base')->assertForbidden();
        $this->actingAs($editor)->get('/knowledge-base-articles')->assertForbidden();
        $this->actingAs($editor)->get(route('kb.download', ['article' => KbArticle::sole(), 'index' => 0]))->assertNotFound();
    }
}
