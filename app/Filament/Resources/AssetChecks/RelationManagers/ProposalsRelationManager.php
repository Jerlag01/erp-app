<?php

namespace App\Filament\Resources\AssetChecks\RelationManagers;

use App\Filament\Resources\AssetChecks\Actions\ProposeAssetAction;
use App\Models\AssetCheck;
use App\Models\AssetProposal;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Equipment the team added to the control list, with the administrator's decision.
 */
class ProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'proposals';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedPlusCircle;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.asset_checks.proposals');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var AssetCheck $ownerRecord */
        $count = $ownerRecord->proposals()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'proposer', 'asset']))
            ->emptyStateHeading(__('erp.asset_checks.no_proposals'))
            ->emptyStateDescription(__('erp.asset_checks.no_proposals_help'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->description(fn (AssetProposal $record): ?string => collect([$record->category?->name, $record->brand, $record->model])->filter()->implode(' · ') ?: null),
                TextColumn::make('serial_number')->label(__('erp.fields.serial_number'))->placeholder('—'),
                TextColumn::make('result')->label(__('erp.asset_checks.result'))->badge()->placeholder('—'),
                TextColumn::make('remark')->label(__('erp.asset_checks.remark'))->placeholder('—')->wrap(),
                TextColumn::make('created_at')
                    ->label(__('erp.asset_checks.proposed_by'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (AssetProposal $record): ?string => $record->proposer?->name),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (AssetProposal $record): ?string => $record->asset?->asset_tag ?? $record->rejection_reason),
            ])
            ->headerActions([
                ProposeAssetAction::make()->visible(fn (): bool => Gate::allows('fill', $this->getOwnerRecord())),
            ])
            ->recordActions([
                DeleteAction::make()->label(__('erp.asset_checks.withdraw')),
            ]);
    }
}
