<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\Peppol\PeppolGateway;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A Peppol / UBL file from a supplier or downloaded from the access point portal.
            Action::make('importUbl')
                ->label(__('erp.finance.expenses.import_ubl'))
                ->icon(Heroicon::OutlinedArrowDownOnSquare)
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()->can('create', Expense::class))
                ->visible(fn (): bool => auth()->user()->can('create', Expense::class))
                ->modalDescription(__('erp.finance.expenses.import_ubl_help'))
                ->schema([
                    FileUpload::make('files')
                        ->hiddenLabel()
                        ->multiple()
                        ->disk('local')
                        ->directory('peppol/uploads')
                        ->rules(['extensions:xml'])
                        ->maxSize(30720)
                        ->required(),
                ])
                ->action(function (array $data, PeppolGateway $peppol): void {
                    $added = 0;
                    $problems = [];

                    foreach ((array) $data['files'] as $path) {
                        try {
                            $result = $peppol->receive((string) Storage::disk('local')->get($path), auth()->id());
                            $added += $result['duplicate'] ? 0 : 1;

                            if ($result['duplicate']) {
                                $problems[] = __('erp.finance.expenses.duplicate', ['number' => $result['expense']->supplier_invoice_number]);
                            }
                        } catch (InvalidArgumentException $exception) {
                            $problems[] = basename($path).': '.$exception->getMessage();
                        } finally {
                            Storage::disk('local')->delete($path);
                        }
                    }

                    Notification::make()
                        ->title(__('erp.finance.expenses.imported', ['count' => $added]))
                        ->body($problems ? implode("\n", $problems) : null)
                        ->status($problems ? 'warning' : 'success')
                        ->send();
                }),
            CreateAction::make()
                ->slideOver()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (ExpenseStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($status === ExpenseStatus::Pending ? (Expense::query()->where('status', $status)->count() ?: null) : null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make(__('erp.finance.all'));

        return $tabs;
    }
}
