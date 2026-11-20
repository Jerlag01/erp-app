<?php

namespace App\Filament\Resources\BtvControls;

use App\Enums\NavigationGroup;
use App\Enums\ProjectFileSection;
use App\Filament\Resources\BtvControls\Pages\ManageBtvControls;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BtvControl;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * The BTV controls, apart from the as-builts: per project the day it was requested, the date BTV
 * confirmed and the report afterwards (kept in the project's files under "BTV control").
 */
class BtvControlResource extends Resource
{
    protected static ?string $model = BtvControl::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'btv-controls';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('erp.resources.btv_control.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.btv_control.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::canViewAny() ? static::getEloquentQuery()->whereNull('project_file_id')->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.btv.open_badge');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('project_id')
                    ->label(__('erp.resources.project.singular'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::projectQuery()
                        ->where(fn (Builder $query) => $query->where('reference', 'like', "%{$search}%")->orWhere('cow_code', 'like', "%{$search}%")->orWhere('short_description', 'like', "%{$search}%"))
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Project $project): array => [$project->id => self::projectLabel($project)])
                        ->all())
                    ->options(fn (): array => self::projectQuery()->latest('id')->limit(50)->get()->mapWithKeys(fn (Project $project): array => [$project->id => self::projectLabel($project)])->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($project = Project::query()->find($value)) ? self::projectLabel($project) : null)
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('requested_on')->label(__('erp.btv.requested_on'))->native(false)->displayFormat('d/m/Y')->default(today())->required(),
                DatePicker::make('confirmed_on')->label(__('erp.btv.confirmed_on'))->helperText(__('erp.btv.confirmed_on_help'))->native(false)->displayFormat('d/m/Y'),
                self::reportUpload()->columnSpanFull(),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    /**
     * The report: stored in the project's files ("BTV control"), see ManageBtvControls.
     */
    public static function reportUpload(): FileUpload
    {
        return FileUpload::make('report_upload')
            ->label(__('erp.btv.report'))
            ->helperText(fn (?BtvControl $record): string => ($record?->project_file_id ? __('erp.btv.report_replace').' ' : '').__('erp.btv.report_help'))
            ->disk('local')
            ->directory('btv-uploads')
            ->visibility('private')
            ->storeFileNamesIn('report_upload_name')
            ->rules(['extensions:'.implode(',', ProjectFileSection::BtvControl->allowedExtensions())])
            ->maxSize(51200)
            ->dehydrated();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.category', 'project.workSite', 'report']))
            ->defaultSort('requested_on', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.resources.project.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (BtvControl $record): ?string => $record->project?->subtitle())
                    ->url(fn (BtvControl $record): ?string => $record->project && Gate::allows('view', $record->project) ? ProjectResource::getUrl('view', ['record' => $record->project]) : null)
                    ->searchable(['reference', 'cow_code', 'short_description']),
                TextColumn::make('requested_on')->label(__('erp.btv.requested_on'))->date('d/m/Y')->sortable(),
                TextColumn::make('confirmed_on')->label(__('erp.btv.confirmed_on'))->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->state(fn (BtvControl $record): string => __('erp.btv.statuses.'.$record->status()))
                    ->color(fn (BtvControl $record): string => BtvControl::statusColor($record->status()))
                    ->badge(),
                TextColumn::make('report.name')
                    ->label(__('erp.btv.report'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (BtvControl $record): ?string => $record->reportUrl(), shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('notes')->label(__('erp.fields.notes'))->limit(60)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(collect([BtvControl::REQUESTED, BtvControl::CONFIRMED, BtvControl::REPORTED])->mapWithKeys(fn (string $status): array => [$status => __("erp.btv.statuses.{$status}")])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->withStatus($data['value']) : $query),
            ])
            ->recordActions([
                Action::make('uploadReport')
                    ->label(__('erp.btv.upload_report'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn (BtvControl $record): bool => $record->project_file_id === null && Gate::allows('update', $record))
                    ->authorize(fn (BtvControl $record): bool => Gate::allows('update', $record))
                    ->schema([self::reportUpload()->required()])
                    ->action(fn (BtvControl $record, array $data) => ManageBtvControls::storeReport($record, $data))
                    ->successNotificationTitle(__('erp.btv.report_saved')),
                EditAction::make()
                    ->slideOver()
                    ->using(function (BtvControl $record, array $data): BtvControl {
                        $record->update([...collect($data)->except(['report_upload', 'report_upload_name'])->all(), 'updated_by' => auth()->id()]);
                        ManageBtvControls::storeReport($record, $data);

                        return $record;
                    }),
                DeleteAction::make()->modalDescription(__('erp.btv.delete_help')),
            ]);
    }

    /**
     * @return Builder<Project>
     */
    protected static function projectQuery(): Builder
    {
        return Project::query()->visibleTo(auth()->user(), 'btv_controls')->with('category');
    }

    protected static function projectLabel(Project $project): string
    {
        return trim($project->reference.($project->subtitle() ? ' — '.$project->subtitle() : ''));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBtvControls::route('/'),
        ];
    }
}
