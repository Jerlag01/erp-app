<?php

namespace App\Filament\Resources\WorkSites\Tables;

use App\Enums\BuildingType;
use App\Filament\Resources\WorkSites\Actions\ChangeBuildingTypeAction;
use App\Models\Country;
use App\Models\WorkSite;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['country', 'area.contact', 'contact']))
            ->defaultSort('cow_code')
            ->searchPlaceholder(__('erp.work_sites.search'))
            ->columns([
                ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(36)
                    ->limit(1)
                    ->toggleable(),
                TextColumn::make('cow_code')
                    ->label(__('erp.fields.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->size('lg')
                    ->color(fn (WorkSite $record): ?string => $record->isDecommissioned() ? 'danger' : null)
                    ->extraAttributes(fn (WorkSite $record): array => $record->isDecommissioned() ? ['data-decommissioned' => 'true'] : [])
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('building_type')
                    ->label(__('erp.fields.building_type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('erp.fields.address'))
                    ->state(fn (WorkSite $record): ?string => trim(implode(' ', array_filter([$record->street, $record->house_number, $record->addition]))) ?: null)
                    ->description(fn (WorkSite $record): ?string => trim(implode(' ', array_filter([$record->postal_code, $record->city]))) ?: null)
                    ->placeholder('—')
                    ->searchable(['street', 'city', 'postal_code']),
                TextColumn::make('state')
                    ->label(__('erp.fields.state'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('area.name')
                    ->label(__('erp.resources.work_site_area.singular'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('contact')
                    ->label(__('erp.fields.contact_person'))
                    ->state(fn (WorkSite $record): ?string => $record->effectiveContact()?->label())
                    ->description(fn (WorkSite $record): ?string => $record->effectiveContact()?->phone)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('cow_code')
                    ->label(__('erp.fields.cow_code'))
                    ->schema([
                        TextInput::make('cow_code')
                            ->label(__('erp.fields.cow_code'))
                            ->helperText(__('erp.work_sites.cow_filter_help'))
                            ->placeholder('02'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['cow_code'] ?? null),
                        fn (Builder $query) => $query->where('cow_code', 'like', strtoupper(trim($data['cow_code'])).'%'),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['cow_code'] ?? null) ? __('erp.fields.cow_code').': '.strtoupper($data['cow_code']).'…' : null),
                SelectFilter::make('building_type')
                    ->label(__('erp.fields.building_type'))
                    ->options(BuildingType::class)
                    ->multiple(),
                SelectFilter::make('area')
                    ->label(__('erp.resources.work_site_area.singular'))
                    ->relationship('area', 'name')
                    ->preload(),
                SelectFilter::make('state')
                    ->label(__('erp.fields.state'))
                    ->options(fn (): array => WorkSite::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state', 'state')->all()),
                SelectFilter::make('country')
                    ->label(__('erp.fields.country'))
                    ->options(fn (): array => Country::query()->whereIn('id', WorkSite::query()->select('country_id'))->get()->mapWithKeys(fn (Country $country): array => [$country->id => $country->localName()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $id) => $query->where('country_id', $id))),
            ])
            ->recordActions([
                Action::make('waze')
                    ->label('Waze')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->iconButton()
                    ->color('info')
                    ->tooltip(__('erp.work_sites.open_waze'))
                    ->url(fn (WorkSite $record): ?string => $record->wazeUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (WorkSite $record): bool => $record->wazeUrl() !== null),
                Action::make('googleMaps')
                    ->label('Google Maps')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->iconButton()
                    ->color('success')
                    ->tooltip(__('erp.work_sites.open_google_maps'))
                    ->url(fn (WorkSite $record): ?string => $record->googleMapsUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (WorkSite $record): bool => $record->googleMapsUrl() !== null),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ChangeBuildingTypeAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
