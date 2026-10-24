<?php

namespace App\Http\Controllers;

use App\Enums\WasteRegion;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use App\Support\PdfAccess;
use App\Support\PdfMerger;
use App\Support\WasteTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * PDFs of the waste registry: the full list or a regional battery list (exactly the rows shown
 * on screen, kept for 30 minutes under a token so long selections need no long URL), and the
 * totals per category.
 */
class WastePdfController
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $period
     */
    public static function urlFor(string $list, array $ids, array $period = []): string
    {
        $token = Str::random(40);

        Cache::put("waste-pdf:{$token}", [
            'user' => auth()->id(),
            'list' => WasteRegion::tryFrom($list)?->value ?? 'all',
            'ids' => array_map('intval', $ids),
            'from' => $period['from'] ?? null,
            'until' => $period['until'] ?? null,
        ], now()->addMinutes(30));

        return route('waste.pdf', ['token' => $token]);
    }

    public function list(Request $request, string $token): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class) && PdfAccess::canGenerate($request->user(), 'waste'), 403);

        $export = Cache::get("waste-pdf:{$token}");
        abort_if($export === null || $export['user'] !== $request->user()->id, 404);

        $order = array_flip($export['ids']);
        $entries = WasteEntry::query()->whereKey($export['ids'])->with(['category.parent', 'processor', 'lines'])->get()
            ->sortBy(fn (WasteEntry $entry): int => $order[$entry->id] ?? 0)
            ->values();

        $region = WasteRegion::tryFrom($export['list']);
        $title = $region ? __('erp.waste.batteries_region', ['region' => $region->getLabel()]) : __('erp.waste.registry');

        // A regional list has a row per line of that region.
        $rows = $region
            ? $entries->flatMap(fn (WasteEntry $entry) => $entry->lines->where('region', $region)->map(fn ($line) => ['entry' => $entry, 'line' => $line]))->values()
            : collect();

        return Pdf::loadView('waste.list-pdf', [
            'entries' => $entries,
            'rows' => $rows,
            'title' => $title,
            'region' => $region,
            'period' => WasteTotals::periodLabel($export['from'], $export['until']),
        ])
            ->setPaper('a4', 'landscape')
            ->download(Str::slug($title).'-'.now()->format('Y-m-d').'.pdf');
    }

    public function totals(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class) && PdfAccess::canGenerate($request->user(), 'waste'), 403);

        $period = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
            'all' => ['nullable', 'boolean'],
        ]);

        return Pdf::loadView('waste.totals-pdf', [
            'totals' => WasteTotals::between($period['from'] ?? null, $period['until'] ?? null, (bool) ($period['all'] ?? false)),
            'period' => WasteTotals::periodLabel($period['from'] ?? null, $period['until'] ?? null),
        ])
            ->setPaper('a4', 'landscape')
            ->download('waste-totals-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * The PDF of a battery line.
     */
    public function linePdf(Request $request, WasteEntryLine $line): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class), 403);
        abort_unless($line->hasPdf(), 404);

        return Storage::disk(WasteEntryLine::DISK)->response($line->pdf_path, $line->pdf_name ?: basename($line->pdf_path), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The battery list of a region for one year: a row per line with its own weight (landscape),
     * followed by the PDF of each line in it (as uploaded, portrait), in the same order. Without the qpdf program to
     * join PDFs, a ZIP with the list and the PDFs is downloaded instead.
     */
    public function regional(Request $request, PdfMerger $merger): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class) && PdfAccess::canGenerate($request->user(), 'waste'), 403);

        $data = $request->validate([
            'region' => ['required', Rule::enum(WasteRegion::class)],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $region = WasteRegion::from($data['region']);
        $year = (int) $data['year'];

        $lines = WasteEntryLine::query()
            ->join('waste_entries', 'waste_entries.id', '=', 'waste_entry_lines.waste_entry_id')
            ->select('waste_entry_lines.*')
            ->where('waste_entry_lines.region', $region->value)
            ->whereYear('waste_entries.date', $year)
            ->orderBy('waste_entries.date')
            ->orderBy('waste_entry_lines.waste_entry_id')
            ->orderBy('waste_entry_lines.sort')
            ->with(['entry.category', 'entry.processor'])
            ->get();

        $title = __('erp.waste.batteries_region', ['region' => $region->getLabel()]).' '.$year;
        $overview = Pdf::loadView('waste.list-pdf', [
            'entries' => $lines->pluck('entry')->unique('id')->values(),
            'rows' => $lines->map(fn (WasteEntryLine $line): array => ['entry' => $line->entry, 'line' => $line])->values(),
            'title' => $title,
            'region' => $region,
            'period' => WasteTotals::periodLabel("{$year}-01-01", "{$year}-12-31"),
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();

        $attachments = $lines->filter(fn (WasteEntryLine $line): bool => $line->hasPdf())->values();
        $name = Str::slug($title);

        if ($attachments->isEmpty()) {
            return response($overview, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"']);
        }

        $disk = Storage::disk(WasteEntryLine::DISK);
        $directory = sys_get_temp_dir().'/waste-export-'.Str::random(12);
        mkdir($directory, 0700, true);

        try {
            file_put_contents("{$directory}/0-overview.pdf", $overview);

            if ($merger->available()) {
                $merger->merge(["{$directory}/0-overview.pdf", ...$attachments->map(fn (WasteEntryLine $line): string => $disk->path($line->pdf_path))->all()], "{$directory}/export.pdf");

                return response((string) file_get_contents("{$directory}/export.pdf"), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"']);
            }

            // Without qpdf: the list and the PDFs side by side in a ZIP, in the same order.
            $zip = new ZipArchive;
            $zip->open("{$directory}/export.zip", ZipArchive::CREATE);
            $zip->addFile("{$directory}/0-overview.pdf", '0 - '.$name.'.pdf');

            foreach ($attachments->values() as $index => $line) {
                $zip->addFile($disk->path($line->pdf_path), ($index + 1).' - '.$line->entry->date->format('Y-m-d').' - '.($line->destruction_certificate ?? '').' - '.Str::slug(pathinfo($line->pdf_name ?: 'document', PATHINFO_FILENAME)).'.pdf');
            }

            $zip->close();

            return response((string) file_get_contents("{$directory}/export.zip"), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$name.'.zip"']);
        } finally {
            array_map('unlink', glob("{$directory}/*") ?: []);
            @rmdir($directory);
        }
    }
}
