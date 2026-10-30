<?php

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Models\ParentalLeave;
use Filament\Actions\Action;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Parental leave (ouderschapsverlof, RVA) enabled by administration for this employee: the child (under
 * 12 at the start), the form (full, 1/2, 1/5 or 1/10, each with its longest duration), the period and the
 * certificate. The OV days themselves are entered under Absences, within such a period.
 */
class ParentalLeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'parentalLeaves';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.parental_leave.plural');
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
                TextInput::make('child_name')->label(__('erp.parental_leave.child_name'))->required()->maxLength(150),
                DatePicker::make('child_birth_date')->label(__('erp.parental_leave.child_birth_date'))->native(false)->displayFormat('d/m/Y')->maxDate(today())->required(),
                ToggleButtons::make('form')
                    ->label(__('erp.parental_leave.form'))
                    ->options(collect(ParentalLeave::FORMS)->mapWithKeys(fn (int $months, string $form): array => [$form => __("erp.parental_leave.forms.{$form}").' ('.__('erp.parental_leave.max_months', ['months' => $months]).')'])->all())
                    ->default('full')
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                DatePicker::make('starts_on')->label(__('erp.fields.start_date'))->native(false)->displayFormat('d/m/Y')->required()->live(),
                DatePicker::make('ends_on')
                    ->label(__('erp.fields.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('starts_on')
                    ->required()
                    // The child under 12 at the start, and not longer than the form allows.
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        if (filled($get('child_birth_date')) && filled($get('starts_on')) && filled($value)
                            && ($problem = ParentalLeave::problem((string) $get('form'), (string) $get('child_birth_date'), (string) $get('starts_on'), (string) $value))) {
                            $fail($problem);
                        }
                    }),
                FileUpload::make('document')
                    ->label(__('erp.parental_leave.document'))
                    ->helperText(__('erp.parental_leave.document_help'))
                    ->disk(ParentalLeave::DISK)
                    ->directory('parental-leaves')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxSize(12288)
                    ->storeFileNamesIn('document_name')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->emptyStateHeading(__('erp.parental_leave.none'))
            ->columns([
                TextColumn::make('form')->label(__('erp.parental_leave.form'))->formatStateUsing(fn (string $state): string => __("erp.parental_leave.forms.{$state}"))->badge(),
                TextColumn::make('child_name')->label(__('erp.parental_leave.child_name'))->description(fn (ParentalLeave $record): string => $record->child_birth_date->format('d/m/Y')),
                TextColumn::make('starts_on')->label(__('erp.fields.period'))->formatStateUsing(fn (ParentalLeave $record): string => $record->starts_on->format('d/m/Y').' – '.$record->ends_on->format('d/m/Y')),
                TextColumn::make('days_taken')
                    ->label(__('erp.parental_leave.days_taken'))
                    ->state(fn (ParentalLeave $record): string => rtrim(rtrim(number_format((float) $record->absences()->sum('days'), 1, ',', ''), '0'), ',')),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
            ])
            ->recordActions([
                Action::make('document')
                    ->label(__('erp.parental_leave.document'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (ParentalLeave $record): bool => filled($record->document))
                    ->url(fn (ParentalLeave $record): string => route('parental-leaves.document', $record), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
