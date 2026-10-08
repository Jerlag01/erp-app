<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Support\DueDate;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class InspectionsDue extends TableWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return modules()->assets() && (bool) auth()->user()?->hasPermission('assets.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.due.inspections_due'))
            ->query(fn (): Builder => Asset::query()->visibleTo(auth()->user())->inspectionDue()->with('category'))
            ->defaultSort('next_inspection_date')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('erp.due.nothing_due'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (Asset $record): ?string => $record->category?->name),
                TextColumn::make('next_inspection_date')
                    ->label(__('erp.fields.next_inspection_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Asset $record): string => DueDate::color($record->next_inspection_date))
                    ->description(fn (Asset $record): ?string => DueDate::description($record->next_inspection_date)),
            ]);
    }
}
