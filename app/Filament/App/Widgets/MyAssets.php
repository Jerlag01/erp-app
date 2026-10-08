<?php

namespace App\Filament\App\Widgets;

use App\Enums\AssetLogType;
use App\Filament\Resources\AssetLogs\Schemas\AssetLogForm;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Support\DueDate;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Equipment the employee works with: handed to them, to their team or in their vehicle.
 * Employees allowed to log damage can report it straight from here.
 */
class MyAssets extends TableWidget
{
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return modules()->assets() && Asset::query()->usedBy(auth()->user())->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.my.assets'))
            ->query(fn (): Builder => Asset::query()->usedBy(auth()->user())->with(['category', 'team', 'vehicle'])->withCount('openDamages'))
            ->defaultSort('asset_tag')
            ->paginated([10, 25, 50])
            ->recordUrl(fn (Asset $record): ?string => auth()->user()->can('view', $record) ? AssetResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (Asset $record): ?string => $record->category?->name)
                    ->searchable(),
                TextColumn::make('via')
                    ->label(__('erp.my.via'))
                    ->state(fn (Asset $record): string => match (true) {
                        $record->user_id === auth()->id() => __('erp.my.personal'),
                        $record->vehicle !== null && $record->vehicle->driver_id === auth()->id() => $record->vehicle->plate_number,
                        default => (string) $record->team?->name,
                    })
                    ->badge()
                    ->color('gray'),
                TextColumn::make('next_inspection_date')
                    ->label(__('erp.fields.next_inspection_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Asset $record): string => DueDate::color($record->next_inspection_date))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                Action::make('reportDamage')
                    ->label(__('erp.my.report_damage'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('asset_logs.create'))
                    ->modalHeading(fn (Asset $record): string => __('erp.my.report_damage').': '.$record->asset_tag)
                    ->schema(fn (Schema $schema, Asset $record): Schema => AssetLogForm::configure($schema->model(AssetLog::class), $record))
                    ->fillForm(['type' => AssetLogType::Damage, 'date' => today()->toDateString()])
                    ->action(function (array $data, Asset $record): void {
                        AssetLog::create([...$data, 'asset_id' => $record->id, 'user_id' => auth()->id()]);
                    })
                    ->successNotificationTitle(__('erp.my.damage_reported')),
            ]);
    }
}
