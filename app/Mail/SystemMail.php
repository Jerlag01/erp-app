<?php

namespace App\Mail;

use App\Enums\MailTemplateKey;
use App\Support\MailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A system e-mail built from its editable template (see MailTemplates), with optional attachments.
 */
class SystemMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{subject: string, html: string} */
    public array $rendered;

    /**
     * @param  array<string, scalar|null>  $values
     * @param  list<array{data: string, name: string, mime: string}>  $files
     */
    public function __construct(public MailTemplateKey $key, public string $templateLocale, array $values, public array $files = [])
    {
        $this->rendered = MailTemplates::render($key, $templateLocale, $values);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.system', with: ['body' => $this->rendered['html']]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(fn (array $file): Attachment => Attachment::fromData(fn (): string => $file['data'], $file['name'])->withMime($file['mime']), $this->files);
    }
}
