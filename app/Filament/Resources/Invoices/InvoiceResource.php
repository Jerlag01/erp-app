<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Invoices\Concerns\AuthorizesInvoiceType;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InvoiceResource extends Resource
{
    use AuthorizesInvoiceType;

    protected static ?string $model = Invoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'invoices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'number';

    public static function invoiceType(): InvoiceType
    {
        return InvoiceType::Invoice;
    }

    public static function getModelLabel(): string
    {
        return __('erp.resources.invoice.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.invoice.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->number ?? __('erp.finance.invoices.draft_title');
    }

    public static function getNavigationBadge(): ?string
    {
        $overdue = Invoice::query()->outstanding()->whereDate('due_date', '<', today())->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.finance.invoices.overdue');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema, InvoiceType::Invoice);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
