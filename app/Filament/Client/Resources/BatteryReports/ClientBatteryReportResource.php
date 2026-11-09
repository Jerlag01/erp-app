<?php

namespace App\Filament\Client\Resources\BatteryReports;

use App\Enums\ProjectStatus;
use App\Filament\Client\ClientResource;
use App\Models\Project;
use App\Support\BatteryReports;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Battery dossiers (604)" in the client portal: the finished battery replacements ("to be invoiced" or
 * later) of the projects the client user sees, each with the COW-code, description, the photos of the
 * dossier, its PDFs (measurement report, delivery note, visual inspection, invoice request) and the
 * waste report. See BatteryReports.
 */
class ClientBatteryReportResource extends ClientResource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'battery-dossiers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBattery100;

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return ClientPortal::BATTERY_REPORTS;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->projects();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.battery_reports.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.battery_reports.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.battery_reports.title');
    }

    public static function getEloquentQuery(): Builder
    {
        return BatteryReports::query(ClientPortal::user())->with(['workSite']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 4])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('reference')->label(__('erp.client_portal.project_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold),
                    TextEntry::make('cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->placeholder('—')
                        ->helperText(fn (Project $record): ?string => $record->workSite?->city),
                    TextEntry::make('short_description')->label(__('erp.client_portal.description'))->placeholder('—'),
                    TextEntry::make('status')->label(__('erp.client_portal.status'))->badge(),
                ]),
            ViewEntry::make('battery_documents')
                ->hiddenLabel()
                ->columnSpanFull()
                ->view('filament.client.battery-report'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.battery_reports.none'))
            ->emptyStateDescription(__('erp.client_portal.battery_reports.none_help'))
            ->columns([
                TextColumn::make('reference')->label(__('erp.client_portal.project_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()->sortable(),
                TextColumn::make('cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->searchable()->sortable()->placeholder('—')
                    ->description(fn (Project $record): ?string => $record->workSite?->city),
                TextColumn::make('short_description')->label(__('erp.client_portal.description'))->searchable()->wrap()->placeholder('—'),
                TextColumn::make('status')->label(__('erp.client_portal.status'))->badge()->sortable(),
                TextColumn::make('executed_at')->label(__('erp.client_portal.battery_reports.executed'))->date('d/m/Y')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('erp.client_portal.status'))
                    ->options(collect([ProjectStatus::InvoicingToDo, ProjectStatus::Invoiced, ProjectStatus::Closed])->mapWithKeys(fn (ProjectStatus $status): array => [$status->value => $status->getLabel()])->all()),
            ])
            ->recordActions([
                ViewAction::make()->label(__('erp.client_portal.battery_reports.open')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientBatteryReports::route('/'),
            'view' => Pages\ViewClientBatteryReport::route('/{record}'),
        ];
    }
}
