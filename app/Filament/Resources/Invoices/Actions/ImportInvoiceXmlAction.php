<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Actions\Finance\ImportInvoiceXml;
use App\Models\Expense;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * "Import XML": earlier invoices as UBL files, several at once or in a ZIP. Our own invoices and credit
 * notes come in under their number (needs invoices.create); suppliers' invoices become incoming
 * invoices (needs expenses.create). By default the direction follows the company VAT number.
 */
class ImportInvoiceXmlAction
{
    public static function make(string $default = ImportInvoiceXml::AUTO): Action
    {
        return Action::make('importXml')
            ->label(__('erp.finance.import.action'))
            ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
            ->color('gray')
            ->visible(fn (): bool => self::directions() !== [])
            ->authorize(fn (): bool => self::directions() !== [])
            ->modalDescription(__('erp.finance.import.help'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('erp.finance.import.files'))
                    ->helperText(__('erp.finance.import.files_help'))
                    ->multiple()
                    ->maxFiles(500)
                    ->disk('local')
                    ->directory('finance/xml-uploads')
                    ->rules(['extensions:xml,zip,pdf'])
                    ->maxSize(51200)
                    ->required(),
                Radio::make('direction')
                    ->label(__('erp.finance.import.direction'))
                    ->options(fn (): array => self::directions())
                    ->default(fn (): string => array_key_exists($default, self::directions()) ? $default : array_key_first(self::directions()))
                    ->live()
                    ->required(),
                Toggle::make('mark_paid')
                    ->label(__('erp.finance.import.mark_paid'))
                    ->helperText(__('erp.finance.import.mark_paid_help'))
                    ->visible(fn (Get $get): bool => $get('direction') !== ImportInvoiceXml::PURCHASES),
            ])
            ->action(function (array $data, ImportInvoiceXml $import): void {
                $files = [];

                foreach ((array) $data['files'] as $path) {
                    $files[] = ['name' => basename($path), 'data' => (string) Storage::disk('local')->get($path)];
                    Storage::disk('local')->delete($path);
                }

                $direction = array_key_exists($data['direction'] ?? '', self::directions()) ? $data['direction'] : ImportInvoiceXml::AUTO;
                $canSales = Gate::allows('create', Invoice::class);
                $canPurchases = Gate::allows('create', Expense::class);

                // "Automatic" with only one of the rights only imports that side.
                if ($direction === ImportInvoiceXml::AUTO && ! ($canSales && $canPurchases)) {
                    $direction = $canSales ? ImportInvoiceXml::SALES : ImportInvoiceXml::PURCHASES;
                }

                $result = $import->import($files, $direction, (bool) ($data['mark_paid'] ?? false), auth()->id());

                $lines = [];

                if ($result['duplicates'] !== []) {
                    $lines[] = __('erp.finance.import.duplicates', ['numbers' => implode(', ', array_slice($result['duplicates'], 0, 20)).(count($result['duplicates']) > 20 ? '…' : '')]);
                }

                array_push($lines, ...array_slice($result['problems'], 0, 20));

                Notification::make()
                    ->title(__('erp.finance.import.done', ['sales' => $result['sales'], 'purchases' => $result['purchases']]))
                    ->body($lines ? implode("\n", $lines) : null)
                    ->status($result['problems'] ? 'warning' : 'success')
                    ->persistent($lines !== [])
                    ->send();
            });
    }

    /**
     * The import directions the user may use.
     *
     * @return array<string, string>
     */
    public static function directions(): array
    {
        $sales = Gate::allows('create', Invoice::class);
        $purchases = Gate::allows('create', Expense::class);

        return array_filter([
            ImportInvoiceXml::AUTO => $sales && $purchases ? __('erp.finance.import.directions.auto') : null,
            ImportInvoiceXml::SALES => $sales ? __('erp.finance.import.directions.sales') : null,
            ImportInvoiceXml::PURCHASES => $purchases ? __('erp.finance.import.directions.purchases') : null,
        ]);
    }
}
