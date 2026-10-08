<?php

namespace App\Filament\Resources\ItAssets\Tables;

use App\Models\ItAsset;
use App\Models\ItCategory;
use App\Models\Location;
use App\Models\User;
use App\Support\DueDate;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['model.manufacturer', 'model.category', 'status', 'location', 'assigned']))
            ->defaultSort('asset_tag')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(36)
                    ->defaultImageUrl(fn (ItAsset $record): ?string => $record->model?->image ? asset('storage/'.$record->model->image) : null)
                    ->toggleable(),
                TextColumn::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('model.name')
                    ->label(__('erp.resources.it_model.singular'))
                    ->formatStateUsing(fn (ItAsset $record): string => $record->name ?: $record->model->fullName())
                    ->description(fn (ItAsset $record): ?string => $record->name ? $record->model->fullName() : $record->model->category?->name)
                    ->searchable(['name'])
                    ->wrap(),
                TextColumn::make('serial')
                    ->label(__('erp.fields.serial_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->size('xs')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status.name')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->color(fn (ItAsset $record): string => $record->status->type->getColor()),
                TextColumn::make('assigned_id')
                    ->label(__('erp.it.checked_out_to'))
                    ->state(fn (ItAsset $record): ?string => $record->assignedName())
                    ->icon(fn (ItAsset $record): ?Heroicon => match ($record->assigned_type) {
                        User::class => Heroicon::OutlinedUser,
                        Location::class => Heroicon::OutlinedMapPin,
                        ItAsset::class => Heroicon::OutlinedComputerDesktop,
                        default => null,
                    })
                    ->placeholder('—'),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->locations())
                    ->toggleable(),
                TextColumn::make('hostname')
                    ->label(__('erp.fields.hostname'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty')
                    ->label(__('erp.it.warranty_until'))
                    ->state(fn (ItAsset $record) => $record->warrantyExpires())
                    ->date('d/m/Y')
                    ->color(fn (ItAsset $record): ?string => $record->warrantyExpires()?->isPast() ? 'danger' : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_audit_date')
                    ->label(__('erp.fields.next_audit_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (ItAsset $record): string => DueDate::color($record->next_audit_date))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->relationship('status', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('category')
                    ->label(__('erp.fields.category'))
                    ->options(fn (): array => ItCategory::query()->where('type', 'asset')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $id) => $query->whereHas('model', fn (Builder $query) => $query->where('it_category_id', $id)))),
                SelectFilter::make('model')
                    ->label(__('erp.resources.it_model.singular'))
                    ->relationship('model', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('checked_out')
                    ->label(__('erp.it.checked_out'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('assigned_type'),
                        false: fn (Builder $query) => $query->whereNull('assigned_type'),
                    ),
                SelectFilter::make('location')
                    ->label(__('erp.resources.location.singular'))
                    ->relationship('location', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preload()
                    ->visible(fn (): bool => modules()->locations()),
                Filter::make('audit_due')
                    ->label(__('erp.it.audits_due'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->auditDue()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printLabels')
                        ->label(__('erp.stock.print_labels'))
                        ->icon(Heroicon::OutlinedQrCode)
                        ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('it.labels', ['assets' => $records->modelKeys()])).', "_blank")'))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
