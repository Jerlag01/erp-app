<?php

namespace App\Models;

use App\Enums\KbFormat;
use App\Models\Concerns\HasTranslations;
use Database\Factories\KbArticleFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * A knowledge base article, built like the support-app FAQ: a title and body per language,
 * written as rich text or Markdown, with downloadable files.
 * Pictures in the text live on the public disk (with random names), downloads on the private
 * disk: they are only served to signed-in users through KbDownloadController.
 */
#[Fillable(['kb_category_id', 'title', 'format', 'body', 'attachments', 'attachment_names', 'is_published', 'sort_order', 'updated_by'])]
class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use HasFactory, HasTranslations;

    public const IMAGE_DISK = 'public';

    public const FILE_DISK = 'local';

    /**
     * Largest picture in the text (10 MB); PHP's own upload limit must allow it too.
     */
    public const IMAGE_MAX_KILOBYTES = 10240;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'format' => 'rich',
    ];

    /**
     * @return BelongsTo<KbCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Published articles in a visible category, or everything for knowledge base editors.
     *
     * @param  Builder<KbArticle>  $query
     */
    public function scopeReadableBy(Builder $query, User $user): void
    {
        if (! $user->canEditKnowledgeBase()) {
            $query->where('is_published', true)->whereHas('category', fn (Builder $query) => $query->where('is_visible', true));
        }
    }

    public function isReadableBy(User $user): bool
    {
        return $user->canEditKnowledgeBase() || ($this->is_published && $this->category->is_visible);
    }

    /**
     * The body as safe HTML in the reader's language.
     */
    public function renderedBody(?string $locale = null): HtmlString
    {
        $content = $this->translate('body', $locale);

        $html = match ($this->format) {
            // Raw HTML in Markdown is stripped; editors format with Markdown syntax only.
            KbFormat::Markdown => Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            // The renderer only outputs nodes the editor knows, so stray markup is dropped.
            KbFormat::RichText => RichContentRenderer::make($content)
                ->fileAttachmentsDisk(self::IMAGE_DISK)
                ->fileAttachmentsVisibility('public')
                ->toHtml(),
        };

        return new HtmlString($html);
    }

    /**
     * Plain text of title and body, for searching.
     */
    public function searchableText(?string $locale = null): string
    {
        return $this->translate('title', $locale).' '.strip_tags($this->renderedBody($locale)->toHtml());
    }

    /**
     * @return list<array{index: int, name: string, size: string}>
     */
    public function downloads(): array
    {
        $disk = Storage::disk(self::FILE_DISK);

        return collect(array_values((array) $this->attachments))
            ->map(fn (string $path, int $index): array => ['index' => $index, 'path' => $path])
            ->filter(fn (array $file): bool => $disk->exists($file['path']))
            ->map(fn (array $file): array => [
                'index' => $file['index'],
                'name' => $this->attachment_names[$file['path']] ?? basename($file['path']),
                'size' => Number::fileSize($disk->size($file['path']), precision: 1),
            ])
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        // Filament doesn't delete files that are removed from an upload field.
        static::updated(function (self $article): void {
            $original = $article->getOriginal('attachments');
            $original = is_string($original) ? json_decode($original, true) : $original;
            $removed = array_diff((array) $original, (array) $article->attachments);
            Storage::disk(self::FILE_DISK)->delete(array_values($removed));
        });

        static::deleted(function (self $article): void {
            Storage::disk(self::FILE_DISK)->delete(array_values((array) $article->attachments));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'format' => KbFormat::class,
            'body' => 'array',
            'attachments' => 'array',
            'attachment_names' => 'array',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
