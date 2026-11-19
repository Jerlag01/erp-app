<?php

namespace App\Services\Inbox;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads a raw e-mail (RFC 5322 / MIME) far enough to get the sender, subject, date and the
 * attached files, also those inside a forwarded mail.
 */
class MimeMessage
{
    /**
     * @param  array<string, string>  $headers
     * @param  list<array{name: string, data: string, mime: string}>  $attachments
     */
    public function __construct(public array $headers, public array $attachments) {}

    public static function parse(string $raw, int $depth = 0): self
    {
        [$headers, $body] = self::split($raw);
        $attachments = [];
        self::collect($headers, $body, $attachments, $depth);

        return new self($headers, $attachments);
    }

    public function subject(): ?string
    {
        return isset($this->headers['subject']) ? self::decodeWords($this->headers['subject']) : null;
    }

    public function messageId(): ?string
    {
        return isset($this->headers['message-id']) ? trim($this->headers['message-id'], " <>\t") : null;
    }

    /**
     * @return array{name: ?string, email: ?string}
     */
    public function from(): array
    {
        $from = self::decodeWords($this->headers['from'] ?? '');

        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $from, $match)) {
            return ['name' => trim($match[1]) ?: null, 'email' => strtolower(trim($match[2]))];
        }

        $email = trim($from);

        return ['name' => null, 'email' => $email !== '' ? strtolower($email) : null];
    }

    public function date(): ?string
    {
        try {
            return isset($this->headers['date']) ? CarbonImmutable::parse(preg_replace('/\s*\(.*\)$/', '', $this->headers['date']))->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    protected static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $position = strpos($raw, "\n\n");
        $head = $position === false ? $raw : substr($raw, 0, $position);
        $body = $position === false ? '' : substr($raw, $position + 2);
        $headers = [];

        // Folded header lines continue with a space or tab.
        foreach (preg_split('/\n(?![ \t])/', $head) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $headers[$name] ??= trim(preg_replace('/\n[ \t]+/', ' ', $value));
            }
        }

        return [$headers, $body];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<array{name: string, data: string, mime: string}>  $attachments
     */
    protected static function collect(array $headers, string $body, array &$attachments, int $depth): void
    {
        if ($depth > 5) {
            return;
        }

        [$type, $params] = self::headerValue($headers['content-type'] ?? 'text/plain');

        if (str_starts_with($type, 'multipart/') && filled($params['boundary'] ?? null)) {
            $parts = preg_split('/^--'.preg_quote($params['boundary'], '/').'(?:--)?[ \t]*$/m', $body);
            array_shift($parts);

            foreach ($parts as $part) {
                if (trim($part) === '') {
                    continue;
                }

                [$partHeaders, $partBody] = self::split(ltrim($part, "\n"));
                self::collect($partHeaders, $partBody, $attachments, $depth + 1);
            }

            return;
        }

        $data = self::decodeBody($body, strtolower(trim($headers['content-transfer-encoding'] ?? '7bit')));

        if ($type === 'message/rfc822') {
            array_push($attachments, ...self::parse($data, $depth + 1)->attachments);

            return;
        }

        [$disposition, $dispositionParams] = self::headerValue($headers['content-disposition'] ?? '');
        $name = $dispositionParams['filename'] ?? $params['name'] ?? null;

        if ($name !== null || $disposition === 'attachment' || in_array($type, ['application/pdf', 'application/xml', 'text/xml', 'application/zip'], true)) {
            $extension = match ($type) {
                'application/pdf' => 'pdf',
                'application/zip' => 'zip',
                default => 'xml',
            };
            $attachments[] = ['name' => basename(str_replace('\\', '/', $name ?? 'attachment.'.$extension)), 'data' => $data, 'mime' => $type];
        }
    }

    protected static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default => $body,
        };
    }

    /**
     * "type/sub; a=b; name*=utf-8''…" → ["type/sub", ["a" => "b", "name" => "…"]].
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected static function headerValue(string $value): array
    {
        $segments = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value);
        $main = strtolower(trim((string) array_shift($segments)));
        $params = [];
        $continued = [];

        foreach ($segments as $segment) {
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $segment, 2);
            $key = strtolower(trim($key));
            $val = trim(trim($val), '"');

            // RFC 2231: name*=charset''text, possibly split as name*0*=, name*1*=.
            if (preg_match('/^([a-z]+)\*(\d+)?\*?$/', $key, $match)) {
                $continued[$match[1]][(int) ($match[2] ?? 0)] = $val;

                continue;
            }

            $params[$key] = self::decodeWords($val);
        }

        foreach ($continued as $key => $pieces) {
            ksort($pieces);
            $joined = implode('', $pieces);

            if (preg_match("/^([A-Za-z0-9_\-]*)'[^']*'(.*)$/", $joined, $match)) {
                $decoded = rawurldecode($match[2]);
                $joined = $match[1] !== '' && strtoupper($match[1]) !== 'UTF-8' ? (string) @mb_convert_encoding($decoded, 'UTF-8', $match[1]) : $decoded;
            }

            $params[$key] = $joined;
        }

        return [$main, $params];
    }

    /**
     * Decodes "=?utf-8?B?…?=" encoded words.
     */
    public static function decodeWords(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return trim($value);
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return trim($decoded !== false ? $decoded : $value);
    }
}
