<?php

namespace App\Filament\App\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The knowledge base as employees read it, like the support-app portal: categories with their
 * articles, a search over title and text, and one article at a time (?article=ID).
 */
class KnowledgeBase extends Page
{
    protected static ?string $slug = 'knowledge-base';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::KnowledgeBase;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.app.knowledge-base';

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?int $category = null;

    #[Url]
    public ?int $article = null;

    public static function canAccess(): bool
    {
        return modules()->knowledgeBase() && (bool) auth()->user()?->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.kb.title');
    }

    public static function articleUrl(KbArticle $article): string
    {
        return static::getUrl(['article' => $article->id], panel: 'app');
    }

    public function getTitle(): string
    {
        return $this->currentArticle()?->translate('title') ?? __('erp.kb.title');
    }

    public function getSubheading(): ?string
    {
        return $this->currentArticle() ? $this->currentArticle()->category->translate('name') : __('erp.kb.intro');
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function currentArticle(): ?KbArticle
    {
        if ($this->article === null) {
            return null;
        }

        $article = KbArticle::query()->with('category')->find($this->article);

        abort_if($article === null || ! $article->isReadableBy($this->user()), 404);

        return $article;
    }

    /**
     * Categories with the articles the user may read; empty categories are left out.
     *
     * @return Collection<int, KbCategory>
     */
    public function categories(): Collection
    {
        $user = $this->user();

        return KbCategory::query()
            ->ordered()
            ->unless($user->canEditKnowledgeBase(), fn ($query) => $query->where('is_visible', true))
            ->when($this->category, fn ($query, int $id) => $query->whereKey($id))
            ->with(['articles' => fn ($query) => $query->readableBy($user)])
            ->get()
            ->filter(fn (KbCategory $category): bool => $category->articles->isNotEmpty())
            ->values();
    }

    /**
     * Search results in the user's language, or null when not searching.
     *
     * @return Collection<int, KbArticle>|null
     */
    public function results(): ?Collection
    {
        $search = trim((string) $this->search);

        if (mb_strlen($search) < 2) {
            return null;
        }

        return KbArticle::query()
            ->readableBy($this->user())
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (KbArticle $article): bool => str($article->searchableText())->contains($search, ignoreCase: true))
            ->values();
    }

    /**
     * Other readable articles in the same category.
     *
     * @return Collection<int, KbArticle>
     */
    public function related(KbArticle $article): Collection
    {
        return $article->category->articles()->readableBy($this->user())->whereKeyNot($article->id)->limit(5)->get();
    }

    public function backToOverview(): void
    {
        $this->article = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('erp.kb.edit_article'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->article !== null && $this->user()->can('update', $this->currentArticle()))
                ->url(fn (): string => KbArticleResource::getUrl('edit', ['record' => $this->article])),
            Action::make('manage')
                ->label(__('erp.kb.manage'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->visible(fn (): bool => $this->article === null && $this->user()->canEditKnowledgeBase())
                ->url(fn (): string => KbArticleResource::getUrl()),
        ];
    }
}
