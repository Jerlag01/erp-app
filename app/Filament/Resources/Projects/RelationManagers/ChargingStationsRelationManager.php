<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Models\ChargingStation;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The charging stations of a 608 project, linking to the charging station pages.
 */
class ChargingStationsRelationManager extends RelationManager
{
    protected static string $relationship = 'chargingStations';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBolt;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.charging.stations');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        $count = $ownerRecord->chargingStations()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return modules()->charging()
            && $ownerRecord->category?->code === settings()->get('charging.project_category', '608')
            && Gate::allows('viewAny', ChargingStation::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ace_number')
            ->modifyQueryUsing(fn ($query) => $query->with('workSite'))
            ->recordUrl(fn (ChargingStation $record): string => ChargingStationResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('erp.charging.no_stations'))
            ->columns([
                TextColumn::make('workSite.cow_code')->label(__('erp.charging.cow_code'))->state(fn (ChargingStation $record): ?string => $record->locationLabel())->description(fn (ChargingStation $record): ?string => $record->cityName()),
                TextColumn::make('ace_number')->label(__('erp.charging.ace_number'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('type')->label(__('erp.charging.type'))->badge()->color('gray'),
                TextColumn::make('cluster_number')->label(__('erp.charging.cluster_number'))->placeholder('—'),
                TextColumn::make('online')->label(__('erp.charging.status'))->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'ONLINE' : 'OFFLINE')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                IconColumn::make('in_service')->label(__('erp.charging.in_service'))->boolean(),
            ])
            ->headerActions([
                Action::make('add')
                    ->label(__('erp.charging.add_station'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => ChargingStationResource::getUrl('create', ['project' => $this->getOwnerRecord()->id]))
                    ->visible(fn (): bool => Gate::allows('create', ChargingStation::class)),
            ]);
    }
}
