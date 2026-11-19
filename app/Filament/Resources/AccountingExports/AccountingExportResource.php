<?php

namespace App\Filament\Resources\AccountingExports;

use App\Actions\Finance\BuildAccountingExport;
use App\Enums\NavigationGroup;
use App\Filament\Resources\AccountingExports\Pages\ManageAccountingExports;
use App\Models\AccountingExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use RuntimeException;
use UnitEnum;

/**
 * The exports for the accountant: per period a ZIP with the sales and purchase documents (UBL and
 * PDF) and the journals as CSV, to download or mail to the accountant.
 */
class AccountingExportResource extends Resource
{
    protected static ?string $model = AccountingExport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'accounting-exports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 9;

    public static function getModelLabel(): string
    {
        return __('erp.resources.accounting_export.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.accounting_export.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('period_from')
                    ->label(__('erp.finance.accounting.period'))
                    ->state(fn (AccountingExport $record): string => $record->period_from->format('d/m/Y').' – '.$record->period_to->format('d/m/Y'))
                    ->description(fn (AccountingExport $record): string => collect($record->parts)->map(fn (string $part): string => __("erp.finance.accounting.parts.{$part}"))->implode(' + ')),
                TextColumn::make('sales_count')->label(__('erp.finance.accounting.parts.sales'))->alignEnd(),
                TextColumn::make('purchase_count')->label(__('erp.finance.accounting.parts.purchases'))->alignEnd(),
                TextColumn::make('size')->label(__('erp.finance.accounting.size'))->formatStateUsing(fn (int $state): string => Number::fileSize($state, 1))->alignEnd(),
                TextColumn::make('created_at')->label(__('erp.finance.accounting.made_on'))->dateTime('d/m/Y H:i')->description(fn (AccountingExport $record): ?string => $record->creator?->name),
                TextColumn::make('emailed_to')
                    ->label(__('erp.finance.accounting.emailed'))
                    ->description(fn (AccountingExport $record): ?string => $record->emailed_at?->format('d/m/Y H:i'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('erp.finance.accounting.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (AccountingExport $record): string => route('finance.accounting-export', ['export' => $record])),
                Action::make('send')
                    ->label(__('erp.finance.accounting.send'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->visible(fn (AccountingExport $record): bool => Gate::allows('send', $record))
                    ->authorize(fn (AccountingExport $record): bool => Gate::allows('send', $record))
                    ->schema([
                        TagsInput::make('emails')
                            ->label(__('erp.finance.accounting.accountant_emails'))
                            ->default(fn (): array => BuildAccountingExport::accountantEmails())
                            ->nestedRecursiveRules(['email'])
                            ->required(),
                    ])
                    ->action(function (AccountingExport $record, array $data, BuildAccountingExport $builder): void {
                        try {
                            $builder->send($record, (array) $data['emails'], auth()->user());
                            Notification::make()->title(__('erp.finance.accounting.sent'))->success()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccountingExports::route('/'),
        ];
    }
}
