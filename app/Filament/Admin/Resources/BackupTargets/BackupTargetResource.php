<?php

namespace App\Filament\Admin\Resources\BackupTargets;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\BackupTargets\RelationManagers\BackupsRelationManager;
use App\Models\BackupTarget;
use App\Support\Backups\BackupManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Throwable;
use UnitEnum;

/**
 * System backups (Systeem → Back-ups): targets on a NAS (SFTP, SMB, Synology or a folder on this server),
 * separate from the NAS systems that keep a copy of the documents. Each target has its own schedule, parts
 * (code, database, server files) and number of backups to keep; any backup still on a target can be restored.
 * Only administrators: the records hold the NAS passwords and restoring replaces the platform's data.
 */
class BackupTargetResource extends Resource
{
    protected static ?string $model = BackupTarget::class;

    protected static ?string $slug = 'backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('backups.title');
    }

    public static function getModelLabel(): string
    {
        return __('backups.target');
    }

    public static function getPluralModelLabel(): string
    {
        return __('backups.targets');
    }

    public static function form(Schema $schema): Schema
    {
        $type = fn (Get $get): string => (string) ($get('type') ?: 'sftp');
        $is = fn (string ...$types) => fn (Get $get): bool => in_array($type($get), $types, true);

        return $schema
            ->columns(2)
            ->components([
                Section::make(__('backups.sections.target'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label(__('backups.fields.name'))->placeholder('Synology kantoor')->required()->maxLength(100),
                        Select::make('type')
                            ->label(__('backups.fields.type'))
                            ->options(fn (): array => collect(array_keys(BackupTarget::TYPES))->mapWithKeys(fn (string $type): array => [$type => __("backups.types.{$type}")])->all())
                            ->default('sftp')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                        TextInput::make('host')->label(__('backups.fields.host'))->helperText(__('backups.hints.host'))->placeholder('nas.example.be')
                            ->required(fn (Get $get): bool => $type($get) !== 'local')->visible(fn (Get $get): bool => $type($get) !== 'local')->maxLength(255),
                        TextInput::make('port')->label(__('backups.fields.port'))->helperText(__('backups.hints.port'))->numeric()->minValue(1)->maxValue(65535)
                            ->visible(fn (Get $get): bool => $type($get) !== 'local'),
                        TextInput::make('username')->label(__('backups.fields.username'))->helperText(__('backups.hints.username'))
                            ->required(fn (Get $get): bool => $type($get) !== 'local')->visible(fn (Get $get): bool => $type($get) !== 'local')->maxLength(255),
                        // Empty keeps the current one; never shown again.
                        TextInput::make('password')
                            ->label(__('backups.fields.password'))
                            ->helperText(__('backups.hints.password'))
                            ->password()
                            ->revealable()
                            ->formatStateUsing(fn (): ?string => null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (Get $get, ?BackupTarget $record): bool => $type($get) !== 'local' && blank($record?->password))
                            ->visible(fn (Get $get): bool => $type($get) !== 'local')
                            ->maxLength(500),
                        TextInput::make('share')->label(__('backups.fields.share'))->helperText(__('backups.hints.share'))->placeholder('backup')
                            ->required($is('smb'))->visible($is('smb'))->maxLength(255),
                        TextInput::make('path')->label(__('backups.fields.path'))->helperText(__('backups.hints.path'))->placeholder('/backup/power-erp')
                            ->required($is('synology', 'local'))->maxLength(500),
                        Toggle::make('verify_tls')->label(__('backups.fields.verify_tls'))->default(true)->visible($is('synology')),
                    ]),
                Section::make(__('backups.sections.schedule'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('parts')
                            ->label(__('backups.fields.parts'))
                            ->options(fn (): array => self::partOptions())
                            ->default(BackupTarget::PARTS)
                            ->required()
                            ->columns(3)
                            ->columnSpanFull(),
                        Select::make('frequency')
                            ->label(__('backups.fields.frequency'))
                            ->options(fn (): array => collect(BackupTarget::FREQUENCIES)->mapWithKeys(fn (string $frequency): array => [$frequency => __("backups.frequencies.{$frequency}")])->all())
                            ->default('daily')
                            ->selectablePlaceholder(false)
                            ->required(),
                        TextInput::make('time')->label(__('backups.fields.time'))->helperText(__('backups.hints.time'))->default('02:00')->placeholder('02:00')
                            ->required()->regex('/^([01]\d|2[0-3]):[0-5]\d$/'),
                        TextInput::make('keep')->label(__('backups.fields.keep'))->helperText(__('backups.hints.keep'))->numeric()->integer()->minValue(1)->maxValue(365)->default(14)->required(),
                        Toggle::make('is_active')->label(__('backups.fields.is_active'))->default(true)->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('backups.help').' '.__('backups.nas_note').' '.__('backups.cli'))
            ->emptyStateHeading(__('backups.none'))
            ->columns([
                TextColumn::make('name')->label(__('backups.fields.name'))->weight('bold')->description(fn (BackupTarget $record): string => self::address($record))->searchable(),
                TextColumn::make('type')->label(__('backups.fields.type'))->formatStateUsing(fn (string $state): string => __("backups.types.{$state}"))->badge()->color('gray'),
                TextColumn::make('parts')->label(__('backups.fields.parts'))->badge()->color('gray')->formatStateUsing(fn (string $state): string => __("backups.parts.{$state}")),
                TextColumn::make('frequency')->label(__('backups.fields.frequency'))
                    ->formatStateUsing(fn (string $state, BackupTarget $record): string => __("backups.frequencies.{$state}").($state === 'manual' ? '' : ' · '.$record->time))
                    ->description(fn (BackupTarget $record): string => __('backups.keeps', ['count' => $record->keep])),
                TextColumn::make('last_run_at')->label(__('backups.last'))->dateTime('d/m/Y H:i')->placeholder(__('backups.never'))
                    ->description(fn (BackupTarget $record): ?string => $record->last_status ? __("backups.statuses.{$record->last_status}") : null)
                    ->tooltip(fn (BackupTarget $record): ?string => $record->last_message),
                IconColumn::make('is_active')->label(__('backups.active'))->boolean(),
            ])
            ->recordActions([
                self::testAction(),
                EditAction::make(),
            ]);
    }

    public static function testAction(): Action
    {
        return Action::make('test')
            ->label(__('backups.test'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (BackupTarget $record): void {
                try {
                    $record->transport()->test();
                    Notification::make()->title(__('backups.test_ok'))->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title(__('backups.test_failed', ['error' => $exception->getMessage()]))->danger()->persistent()->send();
                }
            });
    }

    /**
     * Makes a backup now; it runs after the page is answered (it can take minutes), the result is in the log.
     */
    public static function runAction(): Action
    {
        return Action::make('run')
            ->label(__('backups.run'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->requiresConfirmation()
            ->modalDescription(fn (BackupTarget $record): string => __('backups.run_help', ['parts' => collect((array) $record->parts)->map(fn (string $part): string => __("backups.parts.{$part}"))->implode(', ')]))
            ->action(function (BackupTarget $record): void {
                $user = auth()->user();
                \Illuminate\Support\defer(fn () => app(BackupManager::class)->run($record, $user));
                Notification::make()->title(__('backups.started'))->success()->send();
            });
    }

    /**
     * Puts parts of the platform back from any backup still on the target, after typing RESTORE.
     */
    public static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('backups.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading(__('backups.restore'))
            ->modalDescription(__('backups.restore_help'))
            ->modalSubmitActionLabel(__('backups.restore'))
            ->schema(fn (BackupTarget $record): array => [
                Select::make('file')
                    ->label(__('backups.file'))
                    ->helperText(__('backups.file_help'))
                    ->options(fn (): array => self::fileOptions($record))
                    ->searchable()
                    ->required()
                    ->rule('starts_with:'.BackupManager::PREFIX)
                    ->rule('ends_with:.zip'),
                CheckboxList::make('parts')
                    ->label(__('backups.restore_parts'))
                    ->options(fn (): array => self::partOptions())
                    ->default(['database'])
                    ->columns(3),
                Toggle::make('with_env')->label(__('backups.with_env')),
                TextInput::make('confirm')
                    ->label(__('backups.confirm'))
                    ->required()
                    ->autocomplete(false)
                    ->rule(Rule::in(['RESTORE']))
                    ->validationMessages(['in' => __('backups.confirm_word')]),
            ])
            ->action(function (BackupTarget $record, array $data, Action $action): void {
                $parts = array_values(array_intersect(BackupTarget::PARTS, (array) ($data['parts'] ?? [])));

                if ($parts === [] && ! ($data['with_env'] ?? false)) {
                    Notification::make()->title(__('backups.choose_parts'))->danger()->send();
                    $action->halt();
                }

                $user = auth()->user();
                \Illuminate\Support\defer(fn () => app(BackupManager::class)->restore($record, (string) $data['file'], $parts, $user, (bool) ($data['with_env'] ?? false)));
                Notification::make()->title(__('backups.restore_started'))->warning()->persistent()->send();
            });
    }

    /**
     * The backups on the target, newest first: "power-erp-backup-2026-11-17-020001.zip — 17/11/2026 02:00 · 812,4 MB".
     *
     * @return array<string, string>
     */
    public static function fileOptions(BackupTarget $target): array
    {
        return collect(rescue(fn (): array => $target->transport()->list(), [], false))
            ->filter(fn (array $file): bool => str_starts_with($file['name'], BackupManager::PREFIX) && str_ends_with($file['name'], '.zip'))
            ->mapWithKeys(fn (array $file): array => [$file['name'] => $file['name'].' — '.self::fileDetails($file)])
            ->all();
    }

    /**
     * @param  array{name: string, size: ?int, modified: ?int}  $file
     */
    public static function fileDetails(array $file): string
    {
        return implode(' · ', array_filter([
            $file['modified'] ? date('d/m/Y H:i', $file['modified']) : null,
            $file['size'] ? number_format($file['size'] / 1048576, 1, ',', '.').' MB' : null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    public static function partOptions(): array
    {
        return collect(BackupTarget::PARTS)->mapWithKeys(fn (string $part): array => [$part => __("backups.parts.{$part}")])->all();
    }

    public static function address(BackupTarget $target): string
    {
        return $target->type === 'local'
            ? (string) $target->path
            : trim($target->host.($target->port ? ':'.$target->port : '').' '.($target->type === 'smb' ? '//'.$target->share.'/' : '').ltrim((string) $target->path, '/'));
    }

    public static function getRelations(): array
    {
        return [BackupsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackupTargets::route('/'),
            'create' => Pages\CreateBackupTarget::route('/create'),
            'view' => Pages\ViewBackupTarget::route('/{record}'),
            'edit' => Pages\EditBackupTarget::route('/{record}/edit'),
        ];
    }
}
