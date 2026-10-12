<?php

namespace App\Filament\Resources\AssetChecks;

use App\Enums\NavigationGroup;
use App\Filament\Resources\AssetChecks\Pages\ManageAssetChecks;
use App\Filament\Resources\AssetChecks\Pages\ViewAssetCheck;
use App\Filament\Resources\AssetChecks\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\AssetChecks\RelationManagers\ProposalsRelationManager;
use App\Models\AssetCheck;
use App\Models\User;
use App\Policies\AssetCheckPolicy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The yearly equipment control per team. Administrators and "assets.control" publish a list; the team
 * then marks each piece of equipment OK / not OK until the list is closed again.
 */
class AssetCheckResource extends Resource
{
    protected static ?string $model = AssetCheck::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'asset-checks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.asset_checks.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.asset_checks.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof AssetCheck ? __('erp.asset_checks.record_title', ['team' => $record->team?->name, 'year' => $record->year]) : static::getModelLabel();
    }

    /**
     * Managers see every list; team members only the open lists of their teams.
     *
     * @return Builder<AssetCheck>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['team', 'items']);
        $user = auth()->user();

        if ($user instanceof User && ! AssetCheckPolicy::manages($user)) {
            $query->open()->whereIn('team_id', $user->teamIds());
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $open = static::getEloquentQuery()->open()->whereIn('team_id', $user->teamIds())
            ->whereHas('items', fn (Builder $query) => $query->whereNull('result'))
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                TextEntry::make('team.name')->label(ucfirst(__('erp.resources.team.singular'))),
                TextEntry::make('year')->label(__('erp.asset_checks.year')),
                TextEntry::make('status')
                    ->label(__('erp.fields.status'))
                    ->state(fn (AssetCheck $record): string => $record->isOpen() ? __('erp.asset_checks.open') : __('erp.asset_checks.closed'))
                    ->badge()
                    ->color(fn (AssetCheck $record): string => $record->isOpen() ? 'success' : 'gray'),
                TextEntry::make('progress')
                    ->label(__('erp.asset_checks.progress'))
                    ->state(fn (AssetCheck $record): string => self::progress($record)),
                TextEntry::make('remarks')
                    ->label(__('erp.asset_checks.remarks'))
                    ->placeholder(__('erp.asset_checks.no_remarks'))
                    ->helperText(fn (AssetCheck $record): ?string => $record->remarks_at ? __('erp.asset_checks.remarks_by', ['name' => $record->remarksAuthor?->name ?? '—', 'date' => $record->remarks_at->format('d/m/Y H:i')]) : null)
                    ->prose()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->emptyStateHeading(__('erp.asset_checks.empty'))
            ->emptyStateDescription(__('erp.asset_checks.empty_help'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->recordUrl(fn (AssetCheck $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('team.name')->label(ucfirst(__('erp.resources.team.singular')))->sortable()->searchable(),
                TextColumn::make('year')->label(__('erp.asset_checks.year'))->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->state(fn (AssetCheck $record): string => $record->isOpen() ? __('erp.asset_checks.open') : __('erp.asset_checks.closed'))
                    ->badge()
                    ->color(fn (AssetCheck $record): string => $record->isOpen() ? 'success' : 'gray'),
                TextColumn::make('progress')
                    ->label(__('erp.asset_checks.progress'))
                    ->state(fn (AssetCheck $record): string => self::progress($record)),
                TextColumn::make('not_ok')
                    ->label(__('erp.enums.asset_check_result.not_ok'))
                    ->state(fn (AssetCheck $record): int => $record->notOkCount())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('remarks')
                    ->label(__('erp.asset_checks.remarks'))
                    ->limit(60)
                    ->tooltip(fn (AssetCheck $record): ?string => $record->remarks)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('opened_at')
                    ->label(__('erp.asset_checks.opened_at'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (AssetCheck $record): ?string => $record->opener?->name)
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('erp.asset_checks.fill')),
                self::closeAction(),
                self::reopenAction(),
                DeleteAction::make(),
            ]);
    }

    public static function progress(AssetCheck $check): string
    {
        return $check->checkedCount().' / '.$check->items->count();
    }

    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label(__('erp.asset_checks.close'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('erp.asset_checks.close_help'))
            ->visible(fn (AssetCheck $record): bool => $record->isOpen() && AssetCheckPolicy::manages(auth()->user()))
            ->action(function (AssetCheck $record): void {
                $record->update(['closed_at' => now(), 'closed_by' => auth()->id()]);
                Notification::make()->title(__('erp.asset_checks.closed_notice'))->success()->send();
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label(__('erp.asset_checks.reopen'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (AssetCheck $record): bool => ! $record->isOpen() && AssetCheckPolicy::manages(auth()->user()))
            ->action(function (AssetCheck $record): void {
                ManageAssetChecks::publish($record->team, $record->year);
                Notification::make()->title(trans_choice('erp.asset_checks.published', 1))->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            ProposalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssetChecks::route('/'),
            'view' => ViewAssetCheck::route('/{record}'),
        ];
    }
}
