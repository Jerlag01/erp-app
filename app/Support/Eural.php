<?php

namespace App\Support;

/**
 * The European List of Waste (Eural, Commission Decision 2014/955/EU) in Dutch: 20 chapters, 111
 * subchapters and 842 waste codes (an asterisk marks hazardous waste). The waste categories are built
 * from it (subchapters as categories, codes as subcategories); see database/data/eural_nl.json.
 */
class Eural
{
    public const FILE = 'data/eural_nl.json';

    /**
     * @return array{chapters: array<string, string>, subchapters: list<array{0: string, 1: string}>, codes: list<array{0: string, 1: string}>}
     */
    public static function data(): array
    {
        return once(fn (): array => json_decode((string) file_get_contents(database_path(self::FILE)), true));
    }

    /**
     * Chapter number => title, e.g. "16" => "Niet elders in de lijst genoemd afval".
     *
     * @return array<string, string>
     */
    public static function chapters(): array
    {
        return self::data()['chapters'];
    }

    /**
     * The chapter of a waste code ("16 06 01*" → "16"), or null for a code outside the list.
     */
    public static function chapterOf(?string $code): ?string
    {
        $chapter = substr(trim((string) $code), 0, 2);

        return array_key_exists($chapter, self::chapters()) ? $chapter : null;
    }

    /**
     * "16 06 01*" and "160601*" compare alike.
     */
    public static function normalize(?string $code): string
    {
        return preg_replace('/\s+/', '', (string) $code) ?? '';
    }
}
