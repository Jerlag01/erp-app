<?php

namespace App\Filament\Resources\CreditNotes;

use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\CreditNotes\Pages\EditCreditNote;
use App\Filament\Resources\CreditNotes\Pages\ListCreditNotes;
use App\Filament\Resources\CreditNotes\Pages\ViewCreditNote;
use App\Filament\Resources\Invoices\Concerns\AuthorizesInvoiceType;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Credit notes, always made from an invoice: everything is copied and locked, unless someone
 * with "credit_notes.override" switches changes on.
 */
class CreditNoteResource extends Resource
{
    use AuthorizesInvoiceType;

    protected static ?string $model = Invoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'credit-notes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'number';

    public static function invoiceType(): InvoiceType
    {
        return InvoiceType::CreditNote;
    }

    public static function getModelLabel(): string
    {
        return __('erp.resources.credit_note.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.credit_note.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->number ?? __('erp.finance.invoices.draft_title');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema, InvoiceType::CreditNote);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table)
            ->pushColumns([
                TextColumn::make('creditedInvoice.number')
                    ->label(__('erp.finance.pdf.credits'))
                    ->fontFamily(FontFamily::Mono),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditNotes::route('/'),
            'view' => ViewCreditNote::route('/{record}'),
            'edit' => EditCreditNote::route('/{record}/edit'),
        ];
    }
}
