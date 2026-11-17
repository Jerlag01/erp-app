<?php

namespace App\Filament\Admin\Resources\BackupTargets\RelationManagers;

use App\Models\Backup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The log of a backup target: every backup and restore, by whom, what and the result.
 */
class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('backups.log');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->emptyStateHeading(__('backups.no_log'))
            ->columns([
                TextColumn::make('started_at')->label(__('backups.date'))->dateTime('d/m/Y H:i')
                    ->description(fn (Backup $record): ?string => $record->finished_at && $record->started_at ? $record->started_at->diffForHumans($record->finished_at, true) : null),
                TextColumn::make('kind')->label(__('backups.kind'))->badge()->color(fn (string $state): string => $state === 'restore' ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state): string => __("backups.kinds.{$state}")),
                TextColumn::make('parts')->label(__('backups.fields.parts'))->badge()->color('gray')->formatStateUsing(fn (string $state): string => __("backups.parts.{$state}")),
                TextColumn::make('file_name')->label(__('backups.file'))->fontFamily('mono')->placeholder('—')
                    ->description(fn (Backup $record): ?string => $record->size ? number_format($record->size / 1048576, 1, ',', '.').' MB' : null),
                TextColumn::make('status')->label(__('backups.status'))->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success', 'failed' => 'danger', default => 'warning'
                    })
                    ->formatStateUsing(fn (string $state): string => __("backups.statuses.{$state}"))
                    ->description(fn (Backup $record): ?string => $record->message),
                TextColumn::make('user.name')->label(__('backups.by'))->placeholder(__('backups.scheduler')),
            ]);
    }
}
