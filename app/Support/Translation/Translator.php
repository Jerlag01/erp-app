<?php

namespace App\Support\Translation;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Automatic translation through DeepL (Settings > General > Automatic translation): short texts such as
 * category names, from Dutch to French and English. Without a key nothing is translated (the Dutch text
 * is shown). A free key ends in ":fx" and uses DeepL's free endpoint.
 */
class Translator
{
    /** Our language => DeepL's target code. */
    public const TARGETS = ['fr' => 'FR', 'en' => 'EN-GB'];

    public function enabled(): bool
    {
        return $this->key() !== '';
    }

    /**
     * Translates Dutch texts into one language; returns them in the same order.
     *
     * @param  list<string>  $texts
     * @return list<string>
     *
     * @throws RuntimeException when DeepL cannot be reached or refuses
     */
    public function translate(array $texts, string $to, string $from = 'nl'): array
    {
        $key = $this->key();

        if ($key === '' || $texts === []) {
            return $texts;
        }

        $response = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$key])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->endpoint($key), [
                'text' => array_values($texts),
                'source_lang' => strtoupper($from),
                'target_lang' => self::TARGETS[$to] ?? strtoupper($to),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(__('erp.translation.errors.failed', ['status' => $response->status()]));
        }

        return array_map(fn (array $item): string => (string) ($item['text'] ?? ''), (array) $response->json('translations', []));
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

    protected function key(): string
    {
        $value = (string) settings()->get('translation.deepl_key');

        try {
            return $value !== '' ? Crypt::decryptString($value) : '';
        } catch (Throwable) {
            return '';
        }
    }

    protected function endpoint(string $key): string
    {
        return str_ends_with($key, ':fx') ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
    }
}
