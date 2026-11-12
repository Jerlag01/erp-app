<?php

namespace App\Support\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Automatic translation through MyMemory (api.mymemory.translated.net): free, no account, key or payment.
 * Used for short texts such as stock category names, from Dutch to French and English. Without an e-mail
 * address MyMemory allows about 5,000 characters a day; with one (Settings > General > Automatic
 * translation) about 50,000. It can be switched off there; the Dutch text is then shown.
 */
class Translator
{
    public const ENDPOINT = 'https://api.mymemory.translated.net/get';

    /** Our language => MyMemory's language code. */
    public const TARGETS = ['fr' => 'fr-FR', 'en' => 'en-GB'];

    /** MyMemory refuses longer texts. */
    public const MAX_LENGTH = 500;

    public function enabled(): bool
    {
        return (bool) config('services.translation.enabled', true)
            && (bool) (settings()->get('translation.enabled') ?? true);
    }

    /**
     * Translates Dutch texts into one language; returns them in the same order.
     *
     * @param  list<string>  $texts
     * @return list<string>
     *
     * @throws RuntimeException when MyMemory cannot be reached or refuses
     */
    public function translate(array $texts, string $to, string $from = 'nl'): array
    {
        if (! $this->enabled()) {
            return $texts;
        }

        return array_map(fn (string $text): string => $this->one($text, $to, $from), array_values($texts));
    }

    /**
     * One text into every other language: ['fr' => …, 'en' => …]; empty when it could not be translated.
     *
     * @return array<string, string>
     */
    public function fromDutch(string $text): array
    {
        if (! $this->enabled() || trim($text) === '') {
            return [];
        }

        $result = [];

        foreach (array_keys(self::TARGETS) as $locale) {
            try {
                $result[$locale] = $this->translate([$text], $locale)[0] ?? '';
            } catch (Throwable $exception) {
                Log::warning('Automatic translation failed', ['to' => $locale, 'error' => $exception->getMessage()]);
            }
        }

        return array_filter($result, fn (string $value): bool => $value !== '');
    }

    protected function one(string $text, string $to, string $from): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        try {
            $response = Http::acceptJson()->timeout(20)->get(self::ENDPOINT, array_filter([
                'q' => Str::limit($text, self::MAX_LENGTH, ''),
                'langpair' => $from.'|'.(self::TARGETS[$to] ?? $to),
                'de' => filled($email = settings()->get('translation.email')) ? trim((string) $email) : null,
            ]));
        } catch (Throwable $exception) {
            throw new RuntimeException(__('erp.translation.errors.unreachable'), previous: $exception);
        }

        $translated = trim((string) $response->json('responseData.translatedText', ''));

        if ($response->failed() || (int) $response->json('responseStatus', 200) !== 200 || $response->json('quotaFinished') === true || $translated === '' || str_starts_with($translated, 'MYMEMORY WARNING')) {
            throw new RuntimeException(__('erp.translation.errors.failed', ['reason' => trim((string) ($response->json('responseDetails') ?: 'HTTP '.$response->status()))]));
        }

        return $this->tidy($text, html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * MyMemory sometimes answers from its translation memory, with a trailing ";" or "." or a lower-case
     * start the source did not have: follow the source.
     */
    protected function tidy(string $source, string $translated): string
    {
        $last = mb_substr($source, -1);

        if (! in_array($last, ['.', ';', ':', '!', '?'], true)) {
            $translated = rtrim($translated, '.;:!? ');
        }

        $first = mb_substr($source, 0, 1);

        if ($first !== mb_strtolower($first)) {
            $translated = mb_strtoupper(mb_substr($translated, 0, 1)).mb_substr($translated, 1);
        }

        return $translated;
    }
}
