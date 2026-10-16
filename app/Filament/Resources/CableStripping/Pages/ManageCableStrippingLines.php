<?php

namespace App\Filament\Resources\CableStripping\Pages;

use App\Filament\Resources\CableStripping\CableStrippingLineResource;
use App\Models\CableStrippingLine;
use App\Support\PdfAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageCableStrippingLines extends ManageRecords
{
    protected static string $resource = CableStrippingLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The lines shown (filters, search and sorting included) as a PDF overview with totals.
            Action::make('pdf')
                ->label(__('erp.cable_stripping.pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'cable_stripping'))
                ->authorize(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'cable_stripping'))
                ->action(fn (): StreamedResponse => $this->pdf()),
            CreateAction::make()
                ->label(__('erp.cable_stripping.add'))
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }

    protected function pdf(): StreamedResponse
    {
        $lines = $this->getFilteredSortedTableQuery()->with('workers')->get();
        $period = $this->tableFilters['period'] ?? [];

        $pdf = Pdf::loadView('cable-stripping.pdf', [
            'lines' => $lines,
            'from' => $period['from'] ?? null,
            'until' => $period['until'] ?? null,
            'totals' => [
                'hours' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->hours()),
                'weight_before' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->weight_before),
                'weight_after' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->weight_after),
                'cable_before' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->cableBefore()),
                'waste' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->waste()),
                'stripped' => $lines->sum(fn (CableStrippingLine $line): float => (float) $line->strippedCable()),
            ],
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();

        return response()->streamDownload(fn () => print ($pdf), 'kabel-ontmantelen-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
