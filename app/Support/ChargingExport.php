<?php

namespace App\Support;

use App\Models\ChargingStation;
use App\Models\Router;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exports of the charging stations and 4G routers (Excel and PDF), with the columns of the Excel
 * "Overzicht Laadpalen". Passwords and recovery codes are only included when asked for by a user
 * with "charging.secrets" / "routers.secrets"; VPN files never are.
 */
class ChargingExport
{
    /**
     * Column title => value, for the charging stations.
     *
     * @return array<string, \Closure(ChargingStation): mixed>
     */
    public static function stationColumns(bool $withSecrets): array
    {
        $yes = fn (bool $value): string => $value ? 'Ja' : 'Nee';

        return array_filter([
            'Volledig uitgevoerd' => fn (ChargingStation $station): string => $yes($station->fully_executed),
            'CowCode' => fn (ChargingStation $station): ?string => $station->workSite?->cow_code,
            'ACE-number' => fn (ChargingStation $station): ?string => $station->ace_number,
            'Password' => $withSecrets ? fn (ChargingStation $station): ?string => $station->password : null,
            'Recovery Code' => $withSecrets ? fn (ChargingStation $station): ?string => $station->recovery_code : null,
            'EAN SIM' => fn (ChargingStation $station): ?string => $station->sim_ean,
            'SSN SIM' => fn (ChargingStation $station): ?string => $station->sim_ssn,
            'Connectivity Method' => fn (ChargingStation $station): ?string => $station->connectivity?->getLabel(),
            'Licentie' => fn (ChargingStation $station): ?string => $station->license_key,
            'Adres' => fn (ChargingStation $station): ?string => $station->address(),
            'Type' => fn (ChargingStation $station): ?string => mb_strtoupper((string) $station->type?->getLabel()),
            'Waar?' => fn (ChargingStation $station): ?string => $station->workSite?->city,
            'Online/Offline' => fn (ChargingStation $station): string => $station->online ? 'ONLINE' : 'OFFLINE',
            'Backoffice' => fn (ChargingStation $station): ?string => $station->backoffice,
            'Installatiejaar' => fn (ChargingStation $station): ?int => $station->installation_year,
            'Merk' => fn (ChargingStation $station): ?string => $station->brand,
            'Aantal' => fn (ChargingStation $station): ?int => $station->quantity,
            'Cluster (nummer)' => fn (ChargingStation $station): ?int => $station->cluster_number,
            'Systeem' => fn (ChargingStation $station): ?string => $station->system?->getLabel(),
            'In Dienst' => fn (ChargingStation $station): string => $yes($station->in_service),
            'Door' => fn (ChargingStation $station): ?string => $station->commissioned_by,
            'Einde On-site' => fn (ChargingStation $station): ?string => $station->onsite_warranty_end?->format('d/m/Y'),
            'Plannen bijgewerkt?' => fn (ChargingStation $station): string => $yes($station->plans_updated),
        ]);
    }

    /**
     * Column title => value, for the 4G routers.
     *
     * @return array<string, \Closure(Router): mixed>
     */
    public static function routerColumns(bool $withSecrets): array
    {
        return array_filter([
            'CowCode' => fn (Router $router): ?string => $router->workSite?->cow_code,
            'Adres' => fn (Router $router): ?string => $router->workSite?->addressLine(),
            'Type router' => fn (Router $router): ?string => $router->type,
            'Serienummer' => fn (Router $router): ?string => $router->serial_number,
            'EAN SIM' => fn (Router $router): ?string => $router->sim_ean,
            'SSN SIM' => fn (Router $router): ?string => $router->sim_ssn,
            'Gebruikersnaam' => fn (Router $router): ?string => $router->username,
            'Password' => $withSecrets ? fn (Router $router): ?string => $router->password : null,
        ]);
    }

    /**
     * Writes an .xlsx file and returns its temporary path.
     *
     * @param  array<string, \Closure(mixed): mixed>  $columns
     * @param  iterable<mixed>  $records
     */
    public function xlsx(string $title, array $columns, iterable $records): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(Str::limit($title, 28, ''));
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $writer->addRow(Row::fromValues(array_keys($columns), (new Style)->setFontBold()->setBackgroundColor('D9E1F2')));

        foreach ($records as $record) {
            $writer->addRow(Row::fromValues(array_map(fn (\Closure $value): mixed => $value($record) ?? '', array_values($columns))));
        }

        foreach (array_keys($columns) as $index => $label) {
            $sheet->setColumnWidth(in_array($label, ['Adres', 'Licentie', 'Connectivity Method'], true) ? 32 : max(12, mb_strlen($label) + 2), $index + 1);
        }

        $writer->close();

        return $path;
    }

    /**
     * The list as PDF (A4 landscape).
     *
     * @param  array<string, \Closure(mixed): mixed>  $columns
     * @param  iterable<mixed>  $records
     */
    public function pdf(string $title, array $columns, iterable $records): string
    {
        $rows = [];

        foreach ($records as $record) {
            $rows[] = array_map(fn (\Closure $value): string => (string) ($value($record) ?? ''), array_values($columns));
        }

        return Pdf::loadView('charging.export-pdf', [
            'title' => $title,
            'headers' => array_keys($columns),
            'rows' => $rows,
            // Many columns: smaller text.
            'fontSize' => count($columns) > 14 ? 5.5 : 7.5,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();
    }

    public static function filename(string $title, string $extension): string
    {
        return Str::slug($title.' '.now()->format('Y-m-d')).'.'.$extension;
    }
}
