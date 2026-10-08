<?php

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Certificates of an employee (BA4/BA5, VCA, prevention advisor, driving licence, …), each with
 * an optional expiry date and the PDF of the certificate.
 */
class CertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedAcademicCap;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.employees.certificates');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Employee $ownerRecord */
        $attention = $ownerRecord->certificates()->whereNotNull('expires_on')->whereDate('expires_on', '<=', today()->addDays(Employee::EXPIRY_WARNING_DAYS))->count();

        return $attention > 0 ? (string) $attention : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $isDrivingLicence = fn (Get $get): bool => ($get('type') instanceof CertificateType ? $get('type') : CertificateType::tryFrom((string) $get('type'))) === CertificateType::DrivingLicence;

        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->label(__('erp.employees.certificate'))
                    ->options(CertificateType::class)
                    ->required()
                    ->live()
                    ->unique(
                        table: 'employee_certificates',
                        column: 'type',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('employee_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => __('erp.employees.certificate_exists')])
                    ->columnSpanFull(),
                CheckboxList::make('categories')
                    ->label(__('erp.employees.licence_categories'))
                    ->options(array_combine(CertificateType::DRIVING_LICENCE_CATEGORIES, CertificateType::DRIVING_LICENCE_CATEGORIES))
                    ->columns(['default' => 4, 'md' => 8])
                    ->required($isDrivingLicence)
                    ->in(CertificateType::DRIVING_LICENCE_CATEGORIES)
                    ->visible($isDrivingLicence)
                    ->columnSpanFull(),
                DatePicker::make('obtained_on')
                    ->label(__('erp.employees.obtained_on'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(today()),
                DatePicker::make('expires_on')
                    ->label(__('erp.employees.expires_on'))
                    ->helperText(__('erp.employees.expires_on_help'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('obtained_on'),
                FileUpload::make('document')
                    ->label(__('erp.employees.certificate_pdf'))
                    ->disk(Employee::DISK)
                    ->directory(fn (): string => 'employees/'.$this->getOwnerRecord()->getKey().'/certificates')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('document_name')
                    ->columnSpanFull(),
                TextInput::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (EmployeeCertificate $record): string => $record->label())
            ->defaultSort('type')
            ->emptyStateHeading(__('erp.employees.no_certificates'))
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label(__('erp.employees.certificate'))
                    ->state(fn (EmployeeCertificate $record): string => $record->label())
                    ->description(fn (EmployeeCertificate $record): ?string => $record->notes)
                    ->weight('medium'),
                TextColumn::make('obtained_on')
                    ->label(__('erp.employees.obtained_on'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('expires_on')
                    ->label(__('erp.employees.expires_on'))
                    ->state(fn (EmployeeCertificate $record): string => $record->expires_on?->format('d/m/Y') ?? __('erp.employees.does_not_expire'))
                    ->badge()
                    ->color(fn (EmployeeCertificate $record): string => $record->expiryColor())
                    ->icon(fn (EmployeeCertificate $record): ?Heroicon => $record->isExpired() ? Heroicon::OutlinedExclamationTriangle : null),
                IconColumn::make('document')
                    ->label(__('erp.employees.pdf'))
                    ->state(fn (EmployeeCertificate $record): bool => filled($record->document))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedDocumentCheck)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->url(fn (EmployeeCertificate $record): ?string => filled($record->document) ? route('employees.document', ['employee' => $record->employee_id, 'kind' => 'certificate', 'id' => $record->id]) : null, shouldOpenInNewTab: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('erp.employees.add_certificate'))
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label(__('erp.employees.pdf'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->visible(fn (EmployeeCertificate $record): bool => filled($record->document))
                    ->url(fn (EmployeeCertificate $record): string => route('employees.document', ['employee' => $record->employee_id, 'kind' => 'certificate', 'id' => $record->id]), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
