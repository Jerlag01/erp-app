<?php

namespace App\Console\Commands;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Support\Translation\Translator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

/**
 * Fills the missing French and English names of stock categories and items from the Dutch name (the
 * same as saving them). MyMemory's free limit is per day (about 5,000 characters, 50,000 with the
 * e-mail address in Settings > General), so a run does at most --limit names, with a pause between
 * them; run it again on another day for the rest.
 */
#[Signature('stock:translate-names
    {--limit=200 : at most this many categories and items in this run}
    {--sleep=500 : milliseconds to wait between two names}')]
#[Description('Translate the missing French and English names of stock categories and items')]
class TranslateStockNamesCommand extends Command
{
    public function handle(Translator $translator): int
    {
        if (! $translator->enabled()) {
            $this->components->error('Automatic translation is switched off (Settings > General).');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $records = StockCategory::query()->where(fn ($query) => $query->whereNull('name_fr')->orWhere('name_fr', '')->orWhereNull('name_en')->orWhere('name_en', ''))->orderBy('id')->limit($limit)->get()
            ->concat(StockItem::query()->missingTranslations()->orderBy('id')->limit($limit)->get())
            ->take($limit);
        $translated = 0;

        foreach ($records->values() as $index => $record) {
            /** @var StockCategory|StockItem $record */
            if ($index > 0) {
                Sleep::usleep(max(0, (int) $this->option('sleep')) * 1000);
            }

            $record->translateNames();

            if ($record->isDirty()) {
                $record->save();
                $translated++;
            }
        }

        $left = StockItem::query()->missingTranslations()->count()
            + StockCategory::query()->where(fn ($query) => $query->whereNull('name_fr')->orWhere('name_fr', '')->orWhereNull('name_en')->orWhere('name_en', ''))->count();
        $this->components->info("{$translated} name(s) translated, {$left} still without a translation.");

        return self::SUCCESS;
    }
}
