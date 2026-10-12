<?php

namespace App\Filament\Resources\AssetChecks\Pages;

use App\Filament\Resources\AssetChecks\AssetCheckResource;
use App\Models\AssetCheck;
use App\Models\Team;
use App\Models\User;
use App\Policies\AssetCheckPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAssetChecks extends ManageRecords
{
    protected static string $resource = AssetCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('erp.asset_checks.publish'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => AssetCheckPolicy::manages(auth()->user()))
                ->authorize(fn (): bool => AssetCheckPolicy::manages(auth()->user()))
                ->modalDescription(__('erp.asset_checks.publish_help'))
                ->schema([
                    Select::make('teams')
                        ->label(__('erp.resources.team.plural'))
                        ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->required(),
                    TextInput::make('year')
                        ->label(__('erp.asset_checks.year'))
                        ->integer()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->default((int) now()->year)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $teams = Team::query()->whereKey($data['teams'])->get();
                    $teams->each(fn (Team $team) => self::publish($team, (int) $data['year']));

                    Notification::make()->title(trans_choice('erp.asset_checks.published', $teams->count()))->success()->send();
                }),
        ];
    }

    /**
     * Opens (or reopens) the list of a team for a year, adds the team's current equipment and tells
     * the team members in the platform.
     */
    public static function publish(Team $team, int $year): AssetCheck
    {
        $check = AssetCheck::query()->firstOrNew(['team_id' => $team->id, 'year' => $year]);
        $check->fill(['opened_at' => now(), 'opened_by' => auth()->id(), 'closed_at' => null, 'closed_by' => null])->save();
        $check->syncItems();

        $team->members()->where('is_active', true)->get()
            ->reject(fn (User $user): bool => $user->id === auth()->id())
            ->each(fn (User $user) => Notification::make()
                ->title(__('erp.asset_checks.notification', ['year' => $year], $user->preferredLocale()))
                ->body($team->name)
                ->icon('heroicon-o-clipboard-document-check')
                ->info()
                ->actions([
                    Action::make('open')
                        ->label(__('erp.asset_checks.fill', locale: $user->preferredLocale()))
                        ->url(AssetCheckResource::getUrl('view', ['record' => $check], panel: 'app')),
                ])
                ->sendToDatabase($user));

        return $check;
    }
}
