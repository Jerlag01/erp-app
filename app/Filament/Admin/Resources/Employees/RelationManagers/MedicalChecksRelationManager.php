<?php

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Enums\MedicalResult;
use App\Models\Employee;
use App\Models\EmployeeMedicalCheck;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Medical follow-up: the yearly evaluation by the occupational (company) doctor.
 */
class MedicalChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalChecks';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedHeart;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.employees.medical');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Employee $ownerRecord */
        return $ownerRecord->medicalOverdue() ? __('erp.employees.due') : null;
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
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('checked_on')
                    ->label(__('erp.employees.checked_on'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->maxDate(today())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('next_due_on', $state ? Carbon::parse($state)->addMonths(Employee::MEDICAL_INTERVAL_MONTHS)->toDateString() : null)),
                DatePicker::make('next_due_on')
                    ->label(__('erp.employees.next_due_on'))
                    ->helperText(__('erp.employees.next_due_on_help'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today()->addMonths(Employee::MEDICAL_INTERVAL_MONTHS))
                    ->after('checked_on'),
                ToggleButtons::make('result')
                    ->label(__('erp.employees.result'))
                    ->options(MedicalResult::class)
                    ->default(MedicalResult::Fit)
                    ->inline()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('doctor')
                    ->label(__('erp.employees.doctor'))
                    ->placeholder(__('erp.employees.doctor_placeholder'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('remarks')
                    ->label(__('erp.employees.remarks'))
                    ->helperText(fn (Get $get): ?string => $get('result') && $get('result') !== MedicalResult::Fit && $get('result') !== MedicalResult::Fit->value ? __('erp.employees.remarks_help') : null)
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                FileUpload::make('documents')
                    ->label(__('erp.employees.medical_files'))
                    ->helperText(__('erp.employees.medical_files_help'))
                    ->multiple()
                    ->maxFiles(20)
                    ->appendFiles()
                    ->disk(Employee::DISK)
                    ->directory(fn (): string => 'employees/'.$this->getOwnerRecord()->getKey().'/medical')
                    ->visibility('private')
                    ->acceptedFileTypes(EmployeeMedicalCheck::FILE_TYPES)
                    ->maxSize(20480)
                    ->storeFileNamesIn('document_names')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (EmployeeMedicalCheck $record): string => $record->checked_on->format('d/m/Y'))
            ->emptyStateHeading(__('erp.employees.no_medical'))
            ->emptyStateDescription(__('erp.employees.no_medical_help'))
            ->emptyStateIcon(Heroicon::OutlinedHeart)
            ->columns([
                TextColumn::make('checked_on')
                    ->label(__('erp.employees.checked_on'))
                    ->date('d/m/Y')
                    ->description(fn (EmployeeMedicalCheck $record): ?string => $record->doctor),
                TextColumn::make('result')
                    ->label(__('erp.employees.result'))
                    ->badge()
                    ->description(fn (EmployeeMedicalCheck $record): ?string => $record->remarks)
                    ->wrap(),
                TextColumn::make('next_due_on')
                    ->label(__('erp.employees.next_due_on'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('documents')
                    ->label(__('erp.employees.medical_files'))
                    ->listWithLineBreaks()
                    ->formatStateUsing(fn (string $state, EmployeeMedicalCheck $record): HtmlString => new HtmlString(sprintf(
                        '<a href="%s" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">%s</a>',
                        e(route('employees.document', ['employee' => $record->employee_id, 'kind' => 'medical', 'id' => $record->id, 'file' => (int) array_search($state, $record->documents ?? [], true)])),
                        e($record->documentName($state)),
                    )))
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('erp.employees.add_medical'))
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
