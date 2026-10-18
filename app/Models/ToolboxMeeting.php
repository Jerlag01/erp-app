<?php

namespace App\Models;

use Database\Factories\ToolboxMeetingFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * A toolbox meeting: a safety instruction of a month (title, subject in Dutch and French, who gave it),
 * with a text in which images go anywhere and attachments (PDF, PowerPoint, Word, pictures). Once
 * published, every employee reads it — the whole text and every attachment — and signs it.
 */
#[Fillable([
    'title', 'subject_nl', 'subject_fr', 'location', 'meeting_month', 'instructors', 'body',
    'attachments', 'attachment_names', 'published_at', 'created_by',
])]
class ToolboxMeeting extends Model
{
    /** @use HasFactory<ToolboxMeetingFactory> */
    use HasFactory;

    /** Images in the text: public, like the knowledge base. */
    public const IMAGE_DISK = 'public';

    /** Attachments and signatures: private. */
    public const FILE_DISK = 'local';

    public const ATTACHMENT_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.presentation',
        'image/*',
    ];

    public const ATTACHMENT_MAX_KILOBYTES = 51200;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ToolboxMeetingParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ToolboxMeetingParticipant::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @param  Builder<ToolboxMeeting>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * The row of a user (created when they first open the meeting).
     */
    public function participantFor(User $user): ToolboxMeetingParticipant
    {
        return $this->participants()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Who has to sign: active employees of the employee register (or, without that module, all active
     * accounts with a role).
     *
     * @return Collection<int, User>
     */
    public static function expectedUsers(): Collection
    {
        return User::query()
            ->active()
            ->when(
                modules()->employees(),
                fn (Builder $query) => $query->whereHas('employee', fn (Builder $query) => $query->whereNull('left_on')),
                fn (Builder $query) => $query->whereNotNull('role_id'),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * The people on the attendance list: who has to sign now, plus everyone who signed (also people who
     * left since), by name.
     *
     * @return Collection<int, User>
     */
    public function attendees(): Collection
    {
        $signed = User::query()->whereIn('id', $this->participants()->whereNotNull('signed_at')->select('user_id'))->get();

        return static::expectedUsers()->concat($signed)->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function hasText(): bool
    {
        return filled(trim(strip_tags((string) $this->body, '<img>')));
    }

    /**
     * The attachments: position, stored path and original name.
     *
     * @return list<array{index: int, path: string, name: string}>
     */
    public function attachmentList(): array
    {
        $names = (array) $this->attachment_names;

        return array_values(array_map(
            fn (int $index, string $path): array => ['index' => $index, 'path' => $path, 'name' => (string) ($names[$path] ?? basename($path))],
            array_keys($paths = array_values((array) $this->attachments)),
            $paths,
        ));
    }

    /**
     * The text as safe HTML; the renderer only outputs what the editor knows.
     */
    public function renderedBody(): HtmlString
    {
        return new HtmlString(filled($this->body)
            ? RichContentRenderer::make($this->body)->fileAttachmentsDisk(self::IMAGE_DISK)->fileAttachmentsVisibility('public')->toHtml()
            : '');
    }

    /**
     * "september 2026" in Dutch or "septembre 2026" in French (capitalised).
     */
    public function monthLabel(string $locale): string
    {
        return ucfirst($this->meeting_month->locale($locale)->translatedFormat('F Y'));
    }

    public static function companyName(): string
    {
        return (string) (settings()->get('finance.company_name') ?: settings()->get('general.company_name') ?: settings()->siteName());
    }

    /**
     * "Rudy Cools & Alain Dubois".
     */
    public function instructorNames(): string
    {
        $names = array_values(array_filter(array_map('trim', (array) $this->instructors)));

        return count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).' & '.end($names) : ($names[0] ?? '');
    }

    protected static function booted(): void
    {
        // Attachments removed in the form, and all files of a deleted meeting, leave the disk too.
        static::updated(function (self $meeting): void {
            if ($meeting->wasChanged('attachments')) {
                $old = (array) json_decode((string) $meeting->getRawOriginal('attachments'), true);
                Storage::disk(self::FILE_DISK)->delete(array_values(array_diff($old, (array) $meeting->attachments)));
            }
        });

        static::deleting(function (self $meeting): void {
            Storage::disk(self::FILE_DISK)->delete([
                ...array_values((array) $meeting->attachments),
                ...$meeting->participants()->whereNotNull('signature_path')->pluck('signature_path')->all(),
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_month' => 'date',
            'instructors' => 'array',
            'attachments' => 'array',
            'attachment_names' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
