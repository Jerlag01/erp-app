<?php

namespace App\Filament\Admin\Resources\BackupTargets\Pages;

use App\Filament\Admin\Resources\BackupTargets\BackupTargetResource;
use App\Models\BackupTarget;
use App\Support\Backups\BackupManager;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

/**
 * A backup target: test it, back up now, and the backups on it (each one can be restored); the log below.
 */
class ViewBackupTarget extends ViewRecord
{
    protected static string $resource = BackupTargetResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('backups.sections.target'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('type')->label(__('backups.fields.type'))->formatStateUsing(fn (string $state): string => __("backups.types.{$state}")),
                        TextEntry::make('address')->label(__('backups.fields.path'))->state(fn (BackupTarget $record): string => BackupTargetResource::address($record))->fontFamily('mono'),
                        TextEntry::make('parts')->label(__('backups.fields.parts'))->badge()->color('gray')->formatStateUsing(fn (string $state): string => __("backups.parts.{$state}")),
                        TextEntry::make('frequency')->label(__('backups.fields.frequency'))
                            ->formatStateUsing(fn (string $state, BackupTarget $record): string => __("backups.frequencies.{$state}").($state === 'manual' ? '' : ' · '.$record->time))
                            ->helperText(fn (BackupTarget $record): string => __('backups.keeps', ['count' => $record->keep])),
                        TextEntry::make('last_run_at')->label(__('backups.last'))->dateTime('d/m/Y H:i')->placeholder(__('backups.never'))
                            ->helperText(fn (BackupTarget $record): ?string => $record->last_status ? __("backups.statuses.{$record->last_status}").' · '.$record->last_message : null),
                    ]),
                Section::make(__('backups.on_target'))
                    ->description(__('backups.on_target_help'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('files')
                            ->hiddenLabel()
                            ->state(fn (BackupTarget $record): array => $this->files($record))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->fontFamily('mono')
                            ->placeholder(__('backups.no_backups')),
                    ]),
            ]);
    }

    /**
     * @return list<string>
     */
    protected function files(BackupTarget $target): array
    {
        try {
            $files = $target->transport()->list();
        } catch (Throwable $exception) {
            return [__('backups.unreachable', ['error' => $exception->getMessage()])];
        }

        return collect($files)
            ->filter(fn (array $file): bool => str_starts_with($file['name'], BackupManager::PREFIX))
            ->map(fn (array $file): string => $file['name'].'  ('.BackupTargetResource::fileDetails($file).')')
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            BackupTargetResource::testAction(),
            BackupTargetResource::runAction(),
            BackupTargetResource::restoreAction(),
            EditAction::make(),
        ];
    }
}
