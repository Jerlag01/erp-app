<?php

namespace App\Filament\Resources\It;

use App\Actions\It\CheckoutItem;
use App\Enums\ItItemKind;
use App\Models\ItItem;
use App\Models\ItItemAssignment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who has (or used) the pieces of an accessory, consumable or component.
 */
class ItItemAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        /** @var ItItem $ownerRecord */
        return __('erp.it.assignments.'.$ownerRecord->kind->value);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var ItItem $item */
        $item = $this->getOwnerRecord();
        $returnable = $item->kind !== ItItemKind::Consumable;

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'asset']))
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('assignee')
                    ->label(__('erp.it.checked_out_to'))
                    ->state(fn (ItItemAssignment $record): ?string => $record->user?->name ?? $record->asset?->label())
                    ->weight('bold'),
                TextColumn::make('quantity')
                    ->label(__('erp.fields.quantity')),
                TextColumn::make('assigned_at')
                    ->label(__('erp.fields.date'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('returned_at')
                    ->label(__('erp.it.returned_on'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('erp.it.in_use'))
                    ->visible($returnable),
                TextColumn::make('note')
                    ->label(__('erp.fields.note'))
                    ->placeholder('—'),
            ])
            ->filters($returnable ? [
                TernaryFilter::make('open')
                    ->label(__('erp.it.in_use'))
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('returned_at'),
                        false: fn (Builder $query) => $query->whereNotNull('returned_at'),
                    ),
            ] : [])
            ->recordActions([
                Action::make('checkin')
                    ->label(__('erp.it.checkin'))
                    ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (ItItemAssignment $record): bool => $returnable && $record->returned_at === null && (bool) auth()->user()?->hasPermission('it_items.checkout'))
                    ->requiresConfirmation()
                    ->action(fn (ItItemAssignment $record) => app(CheckoutItem::class)->checkin($record))
                    ->successNotificationTitle(__('erp.it.checked_in_done')),
            ]);
    }
}
