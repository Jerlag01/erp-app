<?php

namespace App\Support;

use App\Models\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The QR codes of the stock items as an Excel list for Labelife (DYMO-compatible label 99010, 89 × 28 mm):
 * one row per item, a header row with plain column names, no styling or merged cells, so Labelife's
 * Excel import can bind the "QR" column to a QR code element and the other columns to text elements.
 */
class StockQrExport
{
    /**
     * @param  array{category?: ?int, subcategory?: ?int, manufacturer?: ?int, location?: ?int, active?: bool}  $filters
     * @return Builder<StockItem>
     */
    public function query(array $filters): Builder
    {
        return StockItem::query()
            ->with(['unit', 'manufacturer', 'category'])
            ->when($filters['active'] ?? true, fn (Builder $query) => $query->where('is_active', true))
            ->when($filters['category'] ?? null, fn (Builder $query, $id) => $query->inCategory((int) $id))
            ->when($filters['subcategory'] ?? null, fn (Builder $query, $id) => $query->where('stock_category_id', $id))
            ->when($filters['manufacturer'] ?? null, fn (Builder $query, $id) => $query->where('manufacturer_id', $id))
            ->when($filters['location'] ?? null, fn (Builder $query, $id) => $query->whereHas('levels', fn (Builder $query) => $query->where('location_id', $id)->where('quantity', '>', 0)))
            ->orderBy('name');
    }

    /**
     * Column => value. The QR column holds the item's scan link, as in the QR code on its labels.
     *
     * @return array<string, \Closure(StockItem): string>
     */
    public function columns(string $locale): array
    {
        return [
            'QR' => fn (StockItem $item): string => $item->qrUrl(),
            'Naam' => fn (StockItem $item): string => $item->localizedName($locale),
            'Referentie' => fn (StockItem $item): string => (string) $item->manufacturer_reference,
            'Fabrikant' => fn (StockItem $item): string => (string) $item->manufacturer?->name,
            'Eenheid' => fn (StockItem $item): string => (string) $item->unit?->abbreviation,
            'Categorie' => fn (StockItem $item): string => (string) $item->category?->name,
            'EAN' => fn (StockItem $item): string => (string) $item->ean,
            'Artikel-ID' => fn (StockItem $item): string => (string) $item->id,
        ];
    }

    /**
     * Writes the .xlsx and returns its temporary path.
     *
     * @param  array{category?: ?int, subcategory?: ?int, manufacturer?: ?int, location?: ?int, active?: bool}  $filters
     */
    public function xlsx(array $filters, string $locale): string
    {
        $columns = $this->columns($locale);
        $path = tempnam(sys_get_temp_dir(), 'qr').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Labels');
        $writer->addRow(Row::fromValues(array_keys($columns)));

        foreach ($this->query($filters)->lazy() as $item) {
            $writer->addRow(Row::fromValues(array_map(fn (\Closure $value): string => $value($item), array_values($columns))));
        }

        foreach ([1 => 42, 2 => 40, 3 => 22, 4 => 20, 5 => 10, 6 => 22, 7 => 16, 8 => 10] as $column => $width) {
            $sheet->setColumnWidth($width, $column);
        }

        $writer->close();

        return $path;
    }

    public static function filename(): string
    {
        return Str::slug('qr-codes labelife 99010 '.now()->format('Y-m-d')).'.xlsx';
    }
}
