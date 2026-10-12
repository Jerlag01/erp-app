<?php

namespace App\Filament\Resources\Concerns;

use App\Support\ChargingExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Export" for the lists of charging stations and 4G routers: the records shown (with the active
 * search and filters) as Excel or PDF. Users with "charging.secrets" can include the passwords.
 */
trait ExportsChargingList
{
    /**
     * @return array<string, \Closure(mixed): mixed>
     */
    abstract protected function exportColumns(bool $withSecrets): array;

    abstract protected function exportTitle(): string;

    abstract protected function canExportSecrets(): bool;

    protected function exportAction(): ActionGroup
    {
        $make = fn (string $format, string $label, Heroicon $icon): Action => Action::make('export_'.$format)
            ->label($label)
            ->icon($icon)
            ->modalHeading(__('erp.charging.export_heading', ['format' => $label]))
            ->modalDescription(__('erp.charging.export_help'))
            ->modalSubmitActionLabel(__('erp.charging.export_submit'))
            ->schema([
                Toggle::make('with_secrets')
                    ->label(__('erp.charging.export_with_secrets'))
                    ->helperText(__('erp.charging.export_with_secrets_help'))
                    ->visible(fn (): bool => $this->canExportSecrets()),
            ])
            ->action(fn (array $data): Response => $this->export($format, $this->canExportSecrets() && (bool) ($data['with_secrets'] ?? false)));

        return ActionGroup::make([
            $make('xlsx', 'Excel', Heroicon::OutlinedTableCells),
            $make('pdf', 'PDF', Heroicon::OutlinedDocumentText),
        ])
            ->label(__('erp.charging.export'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
    }

    protected function export(string $format, bool $withSecrets): Response
    {
        $records = $this->getFilteredSortedTableQuery()->with('workSite')->get();
        $columns = $this->exportColumns($withSecrets);
        $title = $this->exportTitle();
        $exporter = app(ChargingExport::class);

        if ($format === 'pdf') {
            $pdf = $exporter->pdf($title, $columns, $records);

            return response()->streamDownload(fn () => print ($pdf), ChargingExport::filename($title, 'pdf'), ['Content-Type' => 'application/pdf']);
        }

        return response()->download($exporter->xlsx($title, $columns, $records), ChargingExport::filename($title, 'xlsx'))->deleteFileAfterSend();
    }
}
