<?php

namespace App\Filament\Resources\Expenses;

use App\Enums\ExpenseStatus;
use App\Enums\NavigationGroup;
use App\Filament\Forms\SignaturePad;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Incoming invoices. Each one waits for validation: someone with "expenses.validate" accepts it
 * with a note and their signature (the date is set by the system), or rejects it.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'expenses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('erp.resources.expense.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.expense.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Expense::query()->where('status', ExpenseStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.finance.expenses.waiting');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('supplier_name')->label(__('erp.finance.expenses.supplier'))->required()->maxLength(255),
                TextInput::make('supplier_vat')
                    ->label(__('erp.finance.clients.vat_number'))
                    ->maxLength(20)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! Client::isValidVat((string) $value)) {
                            $fail(__('erp.finance.vat_invalid'));
                        }
                    }),
                TextInput::make('supplier_invoice_number')
                    ->label(__('erp.finance.expenses.supplier_number'))
                    ->maxLength(60)
                    ->required()
                    // The same supplier invoice is never booked twice.
                    ->unique(table: 'expenses', column: 'supplier_invoice_number', ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('supplier_vat', Client::normaliseVat($get('supplier_vat')))),
                Toggle::make('is_credit_note')->label(__('erp.finance.expenses.is_credit_note'))->inline(false),
                DatePicker::make('invoice_date')->label(__('erp.finance.invoices.issue_date'))->native(false)->displayFormat('d/m/Y')->default(today())->required(),
                DatePicker::make('due_date')->label(__('erp.finance.invoices.due_date'))->native(false)->displayFormat('d/m/Y'),
                TextInput::make('subtotal')->label(__('erp.finance.excl_vat'))->numeric()->step(0.01)->prefix('€')->required()->live(debounce: 500),
                TextInput::make('vat_total')->label(__('erp.finance.vat'))->numeric()->step(0.01)->prefix('€')->default(0)->required()->live(debounce: 500),
                Text::make(fn (Get $get): string => __('erp.finance.incl_vat').': '.Invoice::money((float) $get('subtotal') + (float) $get('vat_total')))->columnSpanFull(),
                TextInput::make('description')->label(__('erp.fields.description'))->maxLength(255)->columnSpanFull(),
                Select::make('project_id')
                    ->label(__('erp.resources.project.singular'))
                    ->options(fn (): array => Project::query()->with('category')->latest()->limit(500)->get()->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' · '.$project->subtitle(), ' ·')])->all())
                    ->searchable()
                    ->visible(fn (): bool => modules()->projects()),
                DatePicker::make('paid_on')->label(__('erp.finance.invoices.paid_on'))->native(false)->displayFormat('d/m/Y'),
                FileUpload::make('document')
                    ->label(__('erp.finance.expenses.document'))
                    ->disk(Expense::DISK)
                    ->directory(fn (): string => 'expenses/'.now()->format('Y/m'))
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('document_name')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('supplier_name')->label(__('erp.finance.expenses.supplier'))->helperText(fn (Expense $record): ?string => $record->supplier_vat),
                TextEntry::make('supplier_invoice_number')->label(__('erp.finance.expenses.supplier_number'))->fontFamily(FontFamily::Mono),
                TextEntry::make('status')->label(__('erp.fields.status'))->badge(),
                TextEntry::make('invoice_date')->label(__('erp.finance.invoices.issue_date'))->date('d/m/Y'),
                TextEntry::make('due_date')->label(__('erp.finance.invoices.due_date'))->date('d/m/Y')->placeholder('—'),
                TextEntry::make('total')
                    ->label(__('erp.finance.incl_vat'))
                    ->state(fn (Expense $record): string => ($record->is_credit_note ? '− ' : '').Invoice::money($record->total))
                    ->helperText(fn (Expense $record): string => __('erp.finance.excl_vat').': '.Invoice::money($record->subtotal).' · '.__('erp.finance.vat').': '.Invoice::money($record->vat_total))
                    ->weight('bold'),
                TextEntry::make('description')->label(__('erp.fields.description'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('document_name')
                    ->label(__('erp.finance.expenses.document'))
                    ->url(fn (Expense $record): ?string => $record->document ? route('finance.expense-document', ['expense' => $record, 'kind' => 'document']) : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextEntry::make('source')
                    ->label(__('erp.finance.expenses.source'))
                    ->formatStateUsing(fn (string $state): string => __("erp.finance.expenses.sources.{$state}"))
                    ->url(fn (Expense $record): ?string => $record->ubl_path ? route('finance.expense-document', ['expense' => $record, 'kind' => 'ubl']) : null),
                Section::make(__('erp.finance.expenses.validation'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (Expense $record): bool => ! $record->isPending())
                    ->schema([
                        TextEntry::make('validator.name')->label(__('erp.finance.expenses.validated_by')),
                        TextEntry::make('validated_at')->label(__('erp.finance.expenses.validated_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('validation_note')->label(__('erp.finance.expenses.note'))->columnSpanFull(),
                        ImageEntry::make('signature')
                            ->label(__('erp.finance.expenses.signature'))
                            ->state(fn (Expense $record): ?string => $record->signature_path ? route('finance.expense-document', ['expense' => $record, 'kind' => 'signature']) : null)
                            ->imageHeight(90)
                            ->visible(fn (Expense $record): bool => filled($record->signature_path)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['validator', 'project']))
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('supplier_name')
                    ->label(__('erp.finance.expenses.supplier'))
                    ->description(fn (Expense $record): ?string => $record->description)
                    ->searchable(['supplier_name', 'supplier_vat', 'supplier_invoice_number', 'description']),
                TextColumn::make('supplier_invoice_number')
                    ->label(__('erp.finance.expenses.supplier_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge(fn (Expense $record): bool => $record->is_credit_note)
                    ->color(fn (Expense $record): ?string => $record->is_credit_note ? 'info' : null),
                TextColumn::make('invoice_date')->label(__('erp.finance.invoices.issue_date'))->date('d/m/Y')->sortable(),
                TextColumn::make('due_date')->label(__('erp.finance.invoices.due_date'))->date('d/m/Y')->placeholder('—')->toggleable(),
                TextColumn::make('subtotal')->label(__('erp.finance.excl_vat'))->money('EUR', locale: 'nl_BE')->alignEnd()->summarize(Sum::make()->money('EUR', locale: 'nl_BE')),
                TextColumn::make('total')->label(__('erp.finance.incl_vat'))->money('EUR', locale: 'nl_BE')->weight('bold')->alignEnd()->sortable()->summarize(Sum::make()->money('EUR', locale: 'nl_BE')),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (Expense $record): ?string => $record->validator ? $record->validator->name.' · '.$record->validated_at?->format('d/m/Y') : null),
                TextColumn::make('source')
                    ->label(__('erp.finance.expenses.source'))
                    ->formatStateUsing(fn (string $state): string => __("erp.finance.expenses.sources.{$state}"))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y'), (int) date('Y') - 6))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereYear('invoice_date', $year))),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label(__('erp.finance.expenses.accept'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (Expense $record): bool => Gate::allows('validate', $record))
                    ->authorize(fn (Expense $record): bool => Gate::allows('validate', $record))
                    ->modalHeading(fn (Expense $record): string => __('erp.finance.expenses.accept_heading', ['supplier' => $record->supplier_name, 'number' => $record->supplier_invoice_number]))
                    ->modalDescription(fn (Expense $record): string => __('erp.finance.expenses.accept_help', ['amount' => Invoice::money($record->total), 'date' => now()->format('d/m/Y H:i')]))
                    ->schema([
                        Textarea::make('note')->label(__('erp.finance.expenses.note'))->helperText(__('erp.finance.expenses.note_help'))->required()->rows(3)->maxLength(2000),
                        SignaturePad::make('signature')->label(__('erp.finance.expenses.signature'))->required(),
                    ])
                    ->action(fn (Expense $record, array $data) => $record->accept(auth()->user(), $data['note'], $data['signature']))
                    ->successNotificationTitle(__('erp.finance.expenses.accepted')),
                Action::make('reject')
                    ->label(__('erp.finance.expenses.reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Expense $record): bool => Gate::allows('validate', $record))
                    ->authorize(fn (Expense $record): bool => Gate::allows('validate', $record))
                    ->schema([
                        Textarea::make('note')->label(__('erp.finance.expenses.reject_reason'))->required()->rows(3)->maxLength(2000),
                    ])
                    ->action(fn (Expense $record, array $data) => $record->reject(auth()->user(), $data['note']))
                    ->successNotificationTitle(__('erp.finance.expenses.rejected')),
                ViewAction::make()->slideOver(),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
