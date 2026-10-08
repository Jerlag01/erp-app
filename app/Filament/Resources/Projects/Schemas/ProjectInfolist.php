<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Models\Project;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Gate;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $isPrivate = fn (Project $record): bool => $record->category->numbering->hasClient();

        return $schema
            ->components([
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
