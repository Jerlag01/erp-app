<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An employee and a toolbox meeting: whether they read the text to the end, which attachments they
 * downloaded, and — once they read everything — their name and written signature.
 */
#[Fillable(['toolbox_meeting_id', 'user_id', 'text_read_at', 'downloaded', 'signed_at', 'signer_name', 'signature_path'])]
class ToolboxMeetingParticipant extends Model
{
    /**
     * @return BelongsTo<ToolboxMeeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ToolboxMeeting::class, 'toolbox_meeting_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function hasDownloaded(string $path): bool
    {
        return in_array($path, (array) $this->downloaded, true);
    }

    public function recordDownload(string $path): void
    {
        if (! $this->hasDownloaded($path)) {
            $this->update(['downloaded' => [...(array) $this->downloaded, $path]]);
        }
    }

    public function recordTextRead(): void
    {
        if ($this->text_read_at === null) {
            $this->update(['text_read_at' => now()]);
        }
    }

    /**
     * Read everything: the text to the end (when there is text) and every attachment (when there are any).
     */
    public function hasReadEverything(): bool
    {
        $meeting = $this->meeting;
        $textRead = ! $meeting->hasText() || $this->text_read_at !== null;
        $allDownloaded = collect($meeting->attachmentList())->every(fn (array $file): bool => $this->hasDownloaded($file['path']));

        return $textRead && $allDownloaded;
    }

    /**
     * Puts the written signature (PNG data URL from the signature pad) and the name on the meeting.
     */
    public function sign(string $name, string $dataUrl): void
    {
        $png = str_starts_with($dataUrl, 'data:image/png;base64,') ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true) : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw new \InvalidArgumentException('The signature is not a valid PNG image.');
        }

        $path = "toolbox/{$this->toolbox_meeting_id}/signature-{$this->user_id}-".Str::random(12).'.png';
        Storage::disk(ToolboxMeeting::FILE_DISK)->put($path, $png);

        $this->update([
            'signer_name' => mb_substr(trim($name), 0, 150),
            'signature_path' => $path,
            'signed_at' => now(),
        ]);
    }

    public function signatureDataUri(): ?string
    {
        $disk = Storage::disk(ToolboxMeeting::FILE_DISK);

        return filled($this->signature_path) && $disk->exists($this->signature_path)
            ? 'data:image/png;base64,'.base64_encode((string) $disk->get($this->signature_path))
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'text_read_at' => 'datetime',
            'signed_at' => 'datetime',
            'downloaded' => 'array',
        ];
    }
}
