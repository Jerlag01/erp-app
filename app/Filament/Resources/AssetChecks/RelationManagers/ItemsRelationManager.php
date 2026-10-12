<?php

namespace App\Filament\Resources\AssetChecks\RelationManagers;

use App\Enums\AssetCheckResult;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetCheck;
use App\Models\AssetCheckItem;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The equipment on a yearly control list: the team picks OK / not OK per piece. OK sets the control
 * date of the equipment to today.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.asset_checks.items');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canFill(): bool
    {
        /** @var AssetCheck $check */
        $check = $this->getOwnerRecord();

        return Gate::allows('fill', $check);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset.category', 'asset.vehicle', 'checker']))
            ->defaultSort(fn (Builder $query) => $query->orderBy(
                Asset::query()->select('asset_tag')->whereColumn('assets.id', 'asset_check_items.asset_id'),
            ))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.asset_checks.no_items'))
            ->columns([
                TextColumn::make('asset.asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->weight('bold')
                    ->searchable()
                    ->url(fn (AssetCheckItem $record): ?string => $record->asset && Gate::allows('view', $record->asset) ? AssetResource::getUrl('view', ['record' => $record->asset]) : null),
                TextColumn::make('asset.name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (AssetCheckItem $record): ?string => collect([$record->asset?->category?->name, $record->asset?->vehicle?->plate_number])->filter()->implode(' · ') ?: null)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('asset.inspection_date')
                    ->label(__('erp.asset_checks.control_date'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                SelectColumn::make('result')
                    ->label(__('erp.asset_checks.result'))
                    ->options(AssetCheckResult::class)
                    ->placeholder(__('erp.asset_checks.not_checked'))
                    ->disabled(fn (): bool => ! $this->canFill())
                    ->updateStateUsing(function (?string $state, AssetCheckItem $record): ?string {
                        abort_unless($this->canFill(), 403);

                        /** @var User $user */
                        $user = auth()->user();
                        $record->record(AssetCheckResult::tryFrom((string) $state), $user);

                        return $state;
                    }),
                TextInputColumn::make('remark')
                    ->label(__('erp.asset_checks.remark'))
                    ->rules(['nullable', 'string', 'max:255'])
                    ->disabled(fn (): bool => ! $this->canFill()),
                TextColumn::make('checked_at')
                    ->label(__('erp.asset_checks.checked_by'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (AssetCheckItem $record): ?string => $record->checker?->name)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label(__('erp.asset_checks.result'))
                    ->options([
                        'unchecked' => __('erp.asset_checks.not_checked'),
                        ...collect(AssetCheckResult::cases())->mapWithKeys(fn (AssetCheckResult $result): array => [$result->value => $result->getLabel()])->all(),
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'unchecked' => $query->whereNull('result'),
                        default => $query->where('result', $data['value']),
                    }),
            ]);
    }
}
