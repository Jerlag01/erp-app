<?php

namespace App\Filament\Resources\AssetLogs\Tables;

use App\Enums\AssetLogType;
use App\Filament\Resources\AssetLogs\Schemas\AssetLogForm;
use App\Models\AssetLog;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Columns and actions shared by the asset page's log and the global damage & repair log.
 */
class AssetLogsTable
{
    public static function configure(Table $table, bool $showAsset = true): Table
    {
        $canUpdate = fn (): bool => (bool) auth()->user()?->hasPermission('asset_logs.update');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'user', 'damage']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('date')
                    ->label(__('erp.fields.date'))
                    ->date('d/m/Y')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable(),
                TextColumn::make('asset.asset_tag')
                    ->label(__('erp.resources.asset.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (AssetLog $record): ?string => $record->asset?->name)
                    ->searchable(['asset_tag', 'name'])
                    ->visible($showAsset),
                TextColumn::make('type')
                    ->label(__('erp.fields.log_type'))
                    ->badge(),
                TextColumn::make('title')
                    ->label(__('erp.fields.title'))
                    ->description(fn (AssetLog $record): ?string => str($record->description)->limit(80)->toString() ?: null)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('severity')
                    ->label(__('erp.fields.severity'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('link')
                    ->label(__('erp.fields.link'))
                    ->state(fn (AssetLog $record): ?string => match (true) {
                        $record->type === AssetLogType::Damage && $record->resolved_at !== null => __('erp.logs.resolved_on', ['date' => $record->resolved_at->format('d/m/Y')]),
                        $record->type === AssetLogType::Damage => __('erp.logs.open'),
                        $record->damage !== null => __('erp.logs.repairs', ['title' => $record->damage->title]),
                        default => null,
                    })
                    ->badge()
                    ->color(fn (AssetLog $record): string => $record->isOpenDamage() ? 'danger' : ($record->type === AssetLogType::Damage ? 'success' : 'gray'))
                    ->placeholder('—'),
                TextColumn::make('cost')
                    ->label(__('erp.fields.cost'))
                    ->money('EUR', locale: 'nl_BE')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label(__('erp.fields.reported_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('erp.fields.log_type'))
                    ->options(AssetLogType::class)
                    ->multiple(),
                Filter::make('open_damages')
                    ->label(__('erp.logs.open_damages'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('type', AssetLogType::Damage)->whereNull('resolved_at')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('registerRepair')
                        ->label(__('erp.logs.register_repair'))
                        ->icon(Heroicon::OutlinedWrenchScrewdriver)
                        ->color('success')
                        ->visible(fn (AssetLog $record): bool => $record->isOpenDamage() && (bool) auth()->user()?->hasPermission('asset_logs.create'))
                        ->modalHeading(fn (AssetLog $record): string => __('erp.logs.register_repair').': '.$record->title)
                        ->schema(fn (Schema $schema, AssetLog $record): Schema => AssetLogForm::configure($schema->model(AssetLog::class), $record->asset))
                        ->fillForm(fn (AssetLog $record): array => [
                            'type' => AssetLogType::Repair,
                            'damage_id' => $record->id,
                            'date' => today()->toDateString(),
                            'title' => __('erp.logs.repair_of', ['title' => $record->title]),
                        ])
                        ->action(function (array $data, AssetLog $record): void {
                            AssetLog::create([...$data, 'asset_id' => $record->asset_id, 'user_id' => auth()->id()]);
                        })
                        ->successNotificationTitle(__('erp.logs.repair_saved')),
                    Action::make('resolve')
                        ->label(__('erp.logs.resolve'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('erp.logs.resolve_help'))
                        ->visible(fn (AssetLog $record): bool => $record->isOpenDamage() && $canUpdate())
                        ->action(fn (AssetLog $record) => $record->resolve()),
                    Action::make('reopen')
                        ->label(__('erp.logs.reopen'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (AssetLog $record): bool => $record->type === AssetLogType::Damage && $record->resolved_at !== null && $canUpdate())
                        ->action(fn (AssetLog $record) => $record->reopen()),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
