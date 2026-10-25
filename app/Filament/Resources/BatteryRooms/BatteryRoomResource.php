<?php

namespace App\Filament\Resources\BatteryRooms;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\BatteryRooms\Pages\CreateBatteryRoom;
use App\Filament\Resources\BatteryRooms\Pages\EditBatteryRoom;
use App\Filament\Resources\BatteryRooms\Pages\ListBatteryRooms;
use App\Filament\Resources\BatteryRooms\Schemas\BatteryRoomForm;
use App\Models\BatteryRoomDossier;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Battery rooms (609): one per project, made with the project (automatically when a 604 in Flanders or
 * Brussels is closed, or by hand with "New"). Filled in here and printed as delivery note and checklist.
 */
class BatteryRoomResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = BatteryRoomDossier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-rooms';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::BatteryRooms;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('battery_rooms.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('battery_rooms.plural');
    }

    /**
     * Making a battery room by hand makes a 609 project too: both permissions are needed.
     */
    public static function canCreate(): bool
    {
        return Gate::allows('create', BatteryRoomDossier::class) && Gate::allows('create', Project::class);
    }

    public static function form(Schema $schema): Schema
    {
        return BatteryRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category', 'project.poNumbers']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('battery_rooms.project'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (BatteryRoomDossier $record): ?string => $record->project?->workSite?->city)
                    ->searchable(),
                TextColumn::make('room_number')->label(__('battery_rooms.fields.room_number'))->searchable()->placeholder('—'),
                TextColumn::make('project.status')->label(__('erp.fields.status'))->badge(),
                TextColumn::make('reference')
                    ->label(__('battery_rooms.fields.client_reference'))
                    ->state(fn (BatteryRoomDossier $record): ?string => $record->client_reference ?: $record->project?->poNumbers->first()?->number)
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('battery_rooms.doc.pending', [], 'nl')),
                TextColumn::make('checklist_progress')
                    ->label(__('battery_rooms.fields.checklist_done'))
                    ->state(fn (BatteryRoomDossier $record): string => implode('/', $record->checklistProgress()))
                    ->badge()
                    ->color(fn (BatteryRoomDossier $record): string => ($progress = $record->checklistProgress())[0] === $progress[1] ? 'success' : 'gray'),
                IconColumn::make('btv_file')->label('BTV')->boolean()->state(fn (BatteryRoomDossier $record): bool => filled($record->btv_file)),
                TextColumn::make('updated_at')->label(__('erp.projects.updated'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatteryRooms::route('/'),
            'create' => CreateBatteryRoom::route('/create'),
            'edit' => EditBatteryRoom::route('/{record}/edit'),
        ];
    }
}
