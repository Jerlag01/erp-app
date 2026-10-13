<?php

namespace App\Filament\Admin\Resources\InterventionReports;

use App\Actions\Interventions\ReviewIntervention;
use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\InterventionReports\Pages\ListInterventionReports;
use App\Filament\Admin\Resources\InterventionReports\Pages\ViewInterventionReport;
use App\Models\InterventionReport;
use App\Models\InterventionReportMaterial;
use App\Models\InterventionReportPosition;
use App\Models\ProjectPoNumber;
use App\Support\PdfAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Closed interventions waiting for review: an administrator approves them with the PO number
 * (ready for invoicing) or sends them back to the employee with a reason.
 */
class InterventionReportResource extends Resource
{
    protected static ?string $model = InterventionReport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'intervention-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Interventions;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.interventions.report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.interventions.reviews');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = InterventionReport::query()->where('status', InterventionReportStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('project.work_order_number')->label(__('erp.interventions.wo_number'))->prefix('WO ')->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->copyable()->placeholder('—'),
                        TextEntry::make('project.reference')->label(__('erp.projects.reference'))->fontFamily(FontFamily::Mono)->copyable(),
                        TextEntry::make('status')->label(__('erp.interventions.review_status'))->badge(),
                        TextEntry::make('cow_code')->label(__('erp.interventions.cow_code'))->fontFamily(FontFamily::Mono)->helperText(fn (InterventionReport $record): ?string => $record->workSite?->addressLine())->placeholder('—'),
                        TextEntry::make('room')->label(__('erp.interventions.room'))->placeholder('—'),
                        TextEntry::make('po_number')->label(__('erp.interventions.po_number'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                        TextEntry::make('categories')->label(__('erp.interventions.categories'))->badge()->state(fn (InterventionReport $record): array => $record->categoryNames())->placeholder('—'),
                        TextEntry::make('project.subcategory')->label(__('erp.interventions.subcategory'))->state(fn (InterventionReport $record): ?string => $record->project->interventionSubcategoryLabel())->placeholder('—'),
                        TextEntry::make('project.short_description')->label(__('erp.projects.short_description'))->placeholder('—'),
                        TextEntry::make('remarks')->label(__('erp.interventions.remarks'))->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make(__('erp.interventions.sections.execution'))
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        TextEntry::make('executed_at')->label(__('erp.interventions.executed_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('workers')->label(__('erp.interventions.executed_by'))->state(fn (InterventionReport $record): array => $record->workerNames())->badge()->color('gray'),
                        TextEntry::make('hours_worked')->label(__('erp.interventions.hours_worked'))->state(fn (InterventionReport $record): string => $record->hoursLabel().' '.__('erp.interventions.hours_suffix')),
                        TextEntry::make('reviewed_at')->label(__('erp.interventions.reviewed'))->state(fn (InterventionReport $record): ?string => $record->reviewed_at ? $record->reviewed_at->format('d/m/Y H:i').' · '.$record->reviewer?->name : null)->placeholder('—'),
                        TextEntry::make('review_note')->label(__('erp.interventions.review_note'))->placeholder('—'),
                    ]),
            ]),
            Section::make(__('erp.interventions.materials'))
                ->columnSpanFull()
                ->columns(2)
                ->schema(array_map(fn (MaterialDirection $direction): TextEntry => TextEntry::make('materials_'.$direction->value)
                    ->label(__('erp.interventions.pdf.materials_'.$direction->value))
                    ->state(fn (InterventionReport $record): array => array_map(fn (InterventionReportMaterial $material): string => $material->quantityLabel().' × '.$material->description, $record->materialsIn($direction)))
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('—'), MaterialDirection::cases())),
            Section::make(__('erp.interventions.positions'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('positions')
                        ->label(__('erp.interventions.positions'))
                        ->state(fn (InterventionReport $record): array => $record->positions->map(fn (InterventionReportPosition $position): string => $position->label())->all())
                        ->listWithLineBreaks()
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—'),
                    TextEntry::make('modified_boards')
                        ->label(__('erp.interventions.modified_boards'))
                        ->state(fn (InterventionReport $record): array => $record->boardNames())
                        ->badge()
                        ->color('gray')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'closer', 'coWorkers']))
            ->defaultSort('executed_at', 'desc')
            ->columns([
                TextColumn::make('project.work_order_number')->label(__('erp.interventions.wo_number'))->prefix('WO ')->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable(),
                TextColumn::make('project.reference')->label(__('erp.projects.reference'))->fontFamily(FontFamily::Mono)->searchable()->description(fn (InterventionReport $record): ?string => $record->project->short_description),
                TextColumn::make('categories')->label(__('erp.interventions.categories'))->badge()->state(fn (InterventionReport $record): array => $record->categoryNames()),
                TextColumn::make('cow_code')->label(__('erp.interventions.cow_code'))->fontFamily(FontFamily::Mono)->searchable(),
                TextColumn::make('executed_at')->label(__('erp.interventions.executed_at'))->dateTime('d/m/Y H:i')->sortable()->description(fn (InterventionReport $record): string => implode(', ', $record->workerNames())),
                TextColumn::make('hours_worked')->label(__('erp.interventions.hours_worked'))->state(fn (InterventionReport $record): string => $record->hoursLabel())->alignEnd(),
                TextColumn::make('status')->label(__('erp.interventions.review_status'))->badge(),
                TextColumn::make('po_number')->label(__('erp.interventions.po_number'))->fontFamily(FontFamily::Mono)->placeholder('—')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                static::pdfAction(),
            ]);
    }

    public static function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'projects'))
            ->url(fn (InterventionReport $record): string => route('interventions.pdf', $record), shouldOpenInNewTab: true);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('erp.interventions.approve'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (InterventionReport $record): bool => Gate::allows('review', $record))
            ->authorize(fn (InterventionReport $record): bool => Gate::allows('review', $record))
            ->modalDescription(__('erp.interventions.approve_help'))
            ->schema([
                TextInput::make('po_number')
                    ->label(__('erp.interventions.po_number'))
                    ->placeholder('4500123456')
                    ->required()
                    ->length(10)
                    ->regex(ProjectPoNumber::PATTERN)
                    ->validationMessages(['regex' => __('erp.projects.po_number_invalid')])
                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                Textarea::make('note')->label(__('erp.interventions.review_note'))->rows(2)->maxLength(2000),
            ])
            ->action(function (InterventionReport $record, array $data): void {
                app(ReviewIntervention::class)->approve($record, auth()->user(), $data['po_number'], $data['note'] ?? null);
            })
            ->successNotificationTitle(__('erp.interventions.approved'));
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('erp.interventions.reject'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (InterventionReport $record): bool => Gate::allows('review', $record))
            ->authorize(fn (InterventionReport $record): bool => Gate::allows('review', $record))
            ->modalDescription(__('erp.interventions.reject_help'))
            ->schema([
                Textarea::make('note')->label(__('erp.interventions.reject_reason'))->required()->rows(3)->maxLength(2000),
            ])
            ->action(function (InterventionReport $record, array $data): void {
                app(ReviewIntervention::class)->reject($record, auth()->user(), $data['note']);
            })
            ->successNotificationTitle(__('erp.interventions.rejected'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterventionReports::route('/'),
            'view' => ViewInterventionReport::route('/{record}'),
        ];
    }
}
