<?php

namespace App\Filament\Resources\AccountingExports\Pages;

use App\Actions\Finance\BuildAccountingExport;
use App\Filament\Resources\AccountingExports\AccountingExportResource;
use App\Models\AccountingExport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ManageAccountingExports extends ManageRecords
{
    protected static string $resource = AccountingExportResource::class;

    protected function getHeaderActions(): array
    {
        $previousMonth = CarbonImmutable::today()->subMonthNoOverflow();

        return [
            Action::make('export')
                ->label(__('erp.finance.accounting.new'))
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->visible(fn (): bool => Gate::allows('create', AccountingExport::class))
                ->authorize(fn (): bool => Gate::allows('create', AccountingExport::class))
                ->modalDescription(__('erp.finance.accounting.new_help'))
                ->schema([
                    Select::make('preset')
                        ->label(__('erp.finance.accounting.preset'))
                        ->options([
                            'previous_month' => __('erp.finance.accounting.presets.previous_month'),
                            'previous_quarter' => __('erp.finance.accounting.presets.previous_quarter'),
                            'this_month' => __('erp.finance.accounting.presets.this_month'),
                            'this_year' => __('erp.finance.accounting.presets.this_year'),
                        ])
                        ->default('previous_month')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            [$from, $until] = self::preset((string) $state);
                            $set('from', $from->toDateString());
                            $set('until', $until->toDateString());
                        })
                        ->columnSpanFull(),
                    DatePicker::make('from')->label(__('erp.banking.from'))->native(false)->displayFormat('d/m/Y')->default($previousMonth->startOfMonth()->toDateString())->required(),
                    DatePicker::make('until')->label(__('erp.banking.until'))->native(false)->displayFormat('d/m/Y')->default($previousMonth->endOfMonth()->toDateString())->afterOrEqual('from')->required(),
                    CheckboxList::make('parts')
                        ->label(__('erp.finance.accounting.contents'))
                        ->options([AccountingExport::SALES => __('erp.finance.accounting.parts.sales'), AccountingExport::PURCHASES => __('erp.finance.accounting.parts.purchases')])
                        ->default([AccountingExport::SALES, AccountingExport::PURCHASES])
                        ->required()
                        ->columnSpanFull(),
                    Toggle::make('only_new')->label(__('erp.finance.accounting.only_new'))->helperText(__('erp.finance.accounting.only_new_help'))->columnSpanFull(),
                    Toggle::make('send')
                        ->label(__('erp.finance.accounting.send_now'))
                        ->default(fn (): bool => BuildAccountingExport::accountantEmails() !== [])
                        ->visible(fn (): bool => Gate::allows('send', new AccountingExport))
                        ->live()
                        ->columnSpanFull(),
                    TagsInput::make('emails')
                        ->label(__('erp.finance.accounting.accountant_emails'))
                        ->default(fn (): array => BuildAccountingExport::accountantEmails())
                        ->nestedRecursiveRules(['email'])
                        ->visible(fn (Get $get): bool => (bool) $get('send'))
                        ->required(fn (Get $get): bool => (bool) $get('send'))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data, BuildAccountingExport $builder): void {
                    $export = $builder->handle(
                        CarbonImmutable::parse($data['from']),
                        CarbonImmutable::parse($data['until']),
                        array_values((array) $data['parts']),
                        (bool) ($data['only_new'] ?? false),
                        auth()->user(),
                    );

                    $body = __('erp.finance.accounting.made', ['sales' => $export->sales_count, 'purchases' => $export->purchase_count]);

                    if (($data['send'] ?? false) && Gate::allows('send', $export)) {
                        try {
                            $builder->send($export, (array) ($data['emails'] ?? []), auth()->user());
                            $body .= ' '.__('erp.finance.accounting.sent');
                        } catch (RuntimeException $exception) {
                            Notification::make()->title($exception->getMessage())->warning()->send();
                        }
                    }

                    Notification::make()
                        ->title(__('erp.finance.accounting.ready'))
                        ->body($body)
                        ->actions([Action::make('download')->label(__('erp.finance.accounting.download'))->url(route('finance.accounting-export', ['export' => $export]))])
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function preset(string $preset): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'previous_quarter' => [$today->subQuarterNoOverflow()->startOfQuarter(), $today->subQuarterNoOverflow()->endOfQuarter()],
            'this_month' => [$today->startOfMonth(), $today],
            'this_year' => [$today->startOfYear(), $today],
            default => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
        };
    }
}
