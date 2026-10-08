<?php

namespace App\Filament\Widgets;

use App\Enums\AssetLogType;
use App\Filament\Resources\AssetLogs\AssetLogResource;
use App\Models\AssetLog;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpenDamages extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return modules()->assets() && (bool) auth()->user()?->hasPermission('asset_logs.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.logs.open_damages'))
            ->query(fn (): Builder => AssetLog::query()->visibleTo(auth()->user())->where('type', AssetLogType::Damage)->whereNull('resolved_at')->with(['asset', 'user']))
            ->defaultSort('date', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (): string => AssetLogResource::getUrl('index', ['filters' => ['open_damages' => ['isActive' => true]]]))
            ->emptyStateHeading(__('erp.logs.no_open_damages'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('date')
                    ->label(__('erp.fields.date'))
                    ->date('d/m/Y')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('asset.asset_tag')
                    ->label(__('erp.resources.asset.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (AssetLog $record): ?string => $record->asset?->name),
                TextColumn::make('title')
                    ->label(__('erp.fields.title'))
                    ->wrap(),
                TextColumn::make('severity')
                    ->label(__('erp.fields.severity'))
                    ->badge(),
                TextColumn::make('user.name')
                    ->label(__('erp.fields.reported_by'))
                    ->placeholder('—'),
            ]);
    }
}
