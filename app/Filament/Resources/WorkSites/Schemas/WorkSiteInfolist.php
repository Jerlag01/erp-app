<?php

namespace App\Filament\Resources\WorkSites\Schemas;

use App\Models\WorkSite;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class WorkSiteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextEntry::make('cow_code')
                                    ->label(__('erp.fields.cow_code'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color(fn (WorkSite $record): ?string => $record->isDecommissioned() ? 'danger' : null)
                                    ->copyable(),
                                TextEntry::make('building_type')
                                    ->label(__('erp.fields.building_type'))
                                    ->badge(),
                                TextEntry::make('area.name')
                                    ->label(__('erp.resources.work_site_area.singular'))
                                    ->placeholder('—'),
                                TextEntry::make('contact')
                                    ->label(__('erp.fields.contact_person'))
                                    ->state(fn (WorkSite $record): ?string => $record->effectiveContact()?->label())
                                    ->helperText(fn (WorkSite $record): ?string => $record->contact === null && $record->area?->contact ? __('erp.work_sites.contact_from_area') : null)
                                    ->placeholder('—'),
                                TextEntry::make('contact_phone')
                                    ->label(__('erp.fields.phone'))
                                    ->state(fn (WorkSite $record): ?string => $record->effectiveContact()?->phone)
                                    ->url(fn (WorkSite $record): ?string => filled($phone = $record->effectiveContact()?->phone) ? 'tel:'.preg_replace('/[^+\d]/', '', $phone) : null)
                                    ->visible(fn (WorkSite $record): bool => filled($record->effectiveContact()?->phone)),
                                TextEntry::make('contact_email')
                                    ->label(__('erp.fields.email'))
                                    ->state(fn (WorkSite $record): ?string => $record->effectiveContact()?->email)
                                    ->url(fn (WorkSite $record): ?string => filled($email = $record->effectiveContact()?->email) ? "mailto:{$email}" : null)
                                    ->visible(fn (WorkSite $record): bool => filled($record->effectiveContact()?->email)),
                            ]),
                        Section::make(__('erp.fields.address'))
                            ->columnSpan(['lg' => 2])
                            ->columns(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('address')
                                    ->hiddenLabel()
                                    ->state(fn (WorkSite $record): ?string => $record->addressLine())
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->placeholder(__('erp.work_sites.no_address'))
                                    ->columnSpanFull(),
                                TextEntry::make('state')
                                    ->label(__('erp.fields.state'))
                                    ->placeholder('—'),
                                TextEntry::make('country')
                                    ->label(__('erp.fields.country'))
                                    ->state(fn (WorkSite $record): ?string => $record->country?->localName())
                                    ->placeholder('—'),
                                Actions::make([
                                    Action::make('openWaze')
                                        ->label(__('erp.work_sites.open_waze'))
                                        ->icon(Heroicon::OutlinedPaperAirplane)
                                        ->color('info')
                                        ->url(fn (WorkSite $record): ?string => $record->wazeUrl(), shouldOpenInNewTab: true),
                                    Action::make('openGoogleMaps')
                                        ->label(__('erp.work_sites.open_google_maps'))
                                        ->icon(Heroicon::OutlinedMapPin)
                                        ->color('success')
                                        ->url(fn (WorkSite $record): ?string => $record->googleMapsUrl(), shouldOpenInNewTab: true),
                                ])
                                    ->visible(fn (WorkSite $record): bool => $record->wazeUrl() !== null)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make(__('erp.sections.pictures'))
                    ->columnSpanFull()
                    ->visible(fn (WorkSite $record): bool => filled($record->images))
                    ->schema([
                        ImageEntry::make('images')
                            ->hiddenLabel()
                            ->disk('public')
                            ->imageHeight(180)
                            ->openUrlInNewTab()
                            ->url(fn (?string $state): ?string => $state ? Storage::disk('public')->url($state) : null),
                    ]),
            ]);
    }
}
