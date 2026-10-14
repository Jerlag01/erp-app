<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Models\Project;
use App\Support\Interventions;
use App\Support\ProjectProgress;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $isPrivate = fn (Project $record): bool => $record->category->numbering->hasClient();

        return $schema
            ->components([
                // The progress bar of the 610–650 projects.
                Section::make(__('erp.project_progress.title'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->compact()
                    ->columnSpanFull()
                    ->description(fn (Project $record): string => __('erp.project_progress.done', ['percentage' => ProjectProgress::percentage($record)]))
                    ->visible(fn (Project $record): bool => ProjectProgress::appliesTo($record))
                    ->schema([
                        ViewEntry::make('progress')
                            ->hiddenLabel()
                            ->view('filament.projects.progress')
                            ->state(fn (Project $record): array => [
                                'steps' => ProjectProgress::steps($record),
                                'percentage' => ProjectProgress::percentage($record),
                                'editable' => Gate::allows('update', $record),
                            ]),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->compact()
                    ->visible(fn (Project $record): bool => $record->isArchived())
                    ->schema([
                        TextEntry::make('archived_at')
                            ->hiddenLabel()
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->color('gray')
                            ->formatStateUsing(fn (Project $record): string => __('erp.projects.archive.archived_on', ['date' => $record->archived_at->format('d/m/Y H:i')])),
                    ]),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                TextEntry::make('reference')
                                    ->label(__('erp.projects.reference'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->copyable(),
                                TextEntry::make('status')
                                    ->label(__('erp.fields.status'))
                                    ->badge(),
                                TextEntry::make('category')
                                    ->label(__('erp.resources.project_category.singular'))
                                    ->state(fn (Project $record): string => $record->category->label())
                                    ->helperText(fn (Project $record): ?string => $record->category->description),
                                TextEntry::make('workSite.cow_code')
                                    ->label(__('erp.resources.work_site.singular'))
                                    ->state(fn (Project $record): ?string => $record->workSite ? trim($record->workSite->cow_code.' · '.$record->workSite->addressLine(), ' ·') : $record->cow_code)
                                    ->url(fn (Project $record): ?string => $record->workSite && modules()->workSites() && Gate::allows('view', $record->workSite) ? WorkSiteResource::getUrl('view', ['record' => $record->workSite]) : null)
                                    ->placeholder('—')
                                    ->hidden($isPrivate),
                                TextEntry::make('work_order_number')
                                    ->label(__('erp.interventions.wo_number'))
                                    ->prefix('WO ')
                                    ->fontFamily(FontFamily::Mono)
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->visible(fn (Project $record): bool => $record->isIntervention()),
                                TextEntry::make('short_description')
                                    ->label(__('erp.projects.short_description'))
                                    ->visible(fn (Project $record): bool => $record->category->has_short_description)
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('poNumbers.number')
                                    ->label(__('erp.projects.po_numbers'))
                                    ->badge()
                                    ->color('gray')
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->hidden($isPrivate)
                                    ->columnSpanFull(),
                                TextEntry::make('notes')
                                    ->label(__('erp.fields.notes'))
                                    ->placeholder('—')
                                    ->prose()
                                    ->columnSpanFull(),
                            ]),
                        Section::make(__('erp.projects.people'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextEntry::make('leaders.name')
                                    ->label(__('erp.projects.leaders'))
                                    ->badge()
                                    ->placeholder('—'),
                                TextEntry::make('teams.name')
                                    ->label(__('erp.resources.team.plural'))
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->visible(fn (): bool => modules()->teams()),
                                TextEntry::make('created_at')
                                    ->label(__('erp.projects.created'))
                                    ->state(fn (Project $record): string => $record->created_at->format('d/m/Y H:i').($record->creator ? ' · '.$record->creator->name : '')),
                            ]),
                    ]),
                Section::make(__('erp.interventions.title'))
                    ->icon(Heroicon::OutlinedWrench)
                    ->columnSpanFull()
                    ->columns(4)
                    ->visible(fn (Project $record): bool => app(Interventions::class)->appliesTo($record))
                    ->schema([
                        TextEntry::make('interventionCategories.name')
                            ->label(__('erp.interventions.categories'))
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('intervention_subcategory')
                            ->label(__('erp.interventions.subcategory'))
                            ->state(fn (Project $record): ?string => $record->interventionSubcategoryLabel())
                            ->placeholder('—'),
                        TextEntry::make('interventionReport.status')
                            ->label(__('erp.interventions.review_status'))
                            ->badge()
                            ->placeholder(__('erp.interventions.not_closed')),
                        TextEntry::make('interventionReport.executed_at')
                            ->label(__('erp.interventions.executed_at'))
                            ->dateTime('d/m/Y H:i')
                            ->helperText(fn (Project $record): ?string => $record->interventionReport ? implode(', ', $record->interventionReport->workerNames()) : null)
                            ->placeholder('—'),
                        TextEntry::make('interventionReport.review_note')
                            ->label(__('erp.interventions.review_note'))
                            ->visible(fn (Project $record): bool => filled($record->interventionReport?->review_note))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('erp.projects.client'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->visible($isPrivate)
                    ->schema([
                        TextEntry::make('client_name')
                            ->label(__('erp.projects.client_name'))
                            ->helperText(fn (Project $record): ?string => $record->client_company),
                        TextEntry::make('client_phone')
                            ->label(__('erp.fields.phone'))
                            ->url(fn (Project $record): ?string => filled($record->client_phone) ? 'tel:'.preg_replace('/[^+\d]/', '', $record->client_phone) : null)
                            ->placeholder('—'),
                        TextEntry::make('client_email')
                            ->label(__('erp.fields.email'))
                            ->url(fn (Project $record): ?string => filled($record->client_email) ? "mailto:{$record->client_email}" : null)
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label(__('erp.fields.address'))
                            ->state(fn (Project $record): ?string => $record->addressLine())
                            ->url(fn (Project $record): ?string => $record->addressLine() ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($record->addressLine()) : null, shouldOpenInNewTab: true)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
