<?php

namespace App\Filament\Resources\BatteryRooms\Pages;

use App\Actions\Batteries\CreateBatteryRoomProject;
use App\Filament\Resources\BatteryRooms\BatteryRoomResource;
use App\Models\Project;
use App\Models\WorkSite;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A battery room made by hand: a new 609 project on a COW site, optionally following a 604 project.
 * The rest is filled in on the edit page.
 */
class CreateBatteryRoom extends CreateRecord
{
    protected static string $resource = BatteryRoomResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('source_project_id')
                    ->label(__('battery_rooms.fields.source_project'))
                    ->helperText(__('battery_rooms.fields.source_project_help'))
                    ->options(fn (): array => Project::query()
                        ->visibleTo(auth()->user())
                        ->whereHas('category', fn (Builder $query) => $query->where('code', '604'))
                        ->whereNotNull('work_site_id')
                        ->orderByDesc('id')
                        ->limit(500)
                        ->pluck('reference', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (?int $state, Set $set) => $state ? $set('work_site_id', Project::query()->find($state)?->work_site_id) : null)
                    ->columnSpanFull(),
                Select::make('work_site_id')
                    ->label(__('battery_rooms.fields.cow_code'))
                    ->getSearchResultsUsing(fn (string $search): array => WorkSite::query()->where('cow_code', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")->orderBy('cow_code')->limit(50)->get()->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' — '.$site->city, ' —')])->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($site = WorkSite::query()->find($value)) ? trim($site->cow_code.' — '.$site->city, ' —') : null)
                    ->searchable()
                    ->required()
                    ->exists('work_sites', 'id'),
                TextInput::make('room_number')
                    ->label(__('battery_rooms.fields.room_number'))
                    ->helperText(__('battery_rooms.fields.room_number_help'))
                    ->maxLength(50),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Only a 604 the user can see may be followed up.
        if (filled($data['source_project_id'] ?? null) && ! Project::query()->visibleTo(auth()->user())->whereKey($data['source_project_id'])->exists()) {
            $data['source_project_id'] = null;
        }

        return app(CreateBatteryRoomProject::class)->handle($data, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return BatteryRoomResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
