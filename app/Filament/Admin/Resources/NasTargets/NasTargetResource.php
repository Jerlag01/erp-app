<?php

namespace App\Filament\Admin\Resources\NasTargets;

use App\Enums\NavigationGroup;
use App\Models\NasSyncItem;
use App\Models\NasTarget;
use App\Support\Nas\NasSync;
use App\Support\Nas\NasTransports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * The NAS systems that keep a copy of every file, for redundancy (see NasSync): each active NAS gets
 * every file, with its own queue. Per NAS: test the connection, copy everything once, retry what failed.
 */
class NasTargetResource extends Resource
{
    protected static ?string $model = NasTarget::class;

    protected static ?string $slug = 'nas-systems';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 5;

    /**
     * Only administrators: the records hold the NAS passwords.
     */
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

    public static function getModelLabel(): string
    {
        return __('erp.nas.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.nas.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $driver = fn (Get $get): string => (string) ($get('driver') ?: NasTransports::SYNOLOGY);

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->placeholder('NAS kantoor')->required()->maxLength(100),
                Toggle::make('is_active')->label(__('erp.nas.enabled'))->helperText(__('erp.nas.enabled_help'))->default(true)->inline(false),
                Select::make('driver')
                    ->label(__('erp.nas.driver'))
                    ->options(collect(NasTransports::ALL)->mapWithKeys(fn (string $driver): array => [$driver => __("erp.nas.drivers.{$driver}")])->all())
                    ->default(NasTransports::SYNOLOGY)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->label(__('erp.nas.url'))
                    ->helperText(__('erp.nas.url_help'))
                    ->placeholder('https://nas.example.be:5001')
                    ->url()
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::SYNOLOGY)
                    ->required(fn (Get $get): bool => $driver($get) === NasTransports::SYNOLOGY)
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('host')
                    ->label(__('erp.nas.host'))
                    ->helperText(__('erp.nas.host_help'))
                    ->placeholder('nas.example.be')
                    ->visible(fn (Get $get): bool => $driver($get) !== NasTransports::SYNOLOGY)
                    ->required(fn (Get $get): bool => $driver($get) !== NasTransports::SYNOLOGY)
                    ->maxLength(255),
                TextInput::make('port')
                    ->label(__('erp.nas.port'))
                    ->helperText(fn (Get $get): string => __('erp.nas.port_help', ['port' => match ($driver($get)) {
                        NasTransports::SMB => 445, NasTransports::FTP => 21, default => 22
                    }]))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->visible(fn (Get $get): bool => $driver($get) !== NasTransports::SYNOLOGY),
                TextInput::make('share')
                    ->label(__('erp.nas.share'))
                    ->helperText(__('erp.nas.share_help'))
                    ->placeholder('ERP')
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::SMB)
                    ->required(fn (Get $get): bool => $driver($get) === NasTransports::SMB)
                    ->maxLength(100),
                TextInput::make('domain')
                    ->label(__('erp.nas.domain'))
                    ->helperText(__('erp.nas.domain_help'))
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::SMB)
                    ->maxLength(100),
                TextInput::make('root')
                    ->label(__('erp.nas.root'))
                    ->helperText(fn (Get $get): string => __(match ($driver($get)) {
                        NasTransports::SMB => 'erp.nas.root_help_smb', NasTransports::SYNOLOGY => 'erp.nas.root_help', default => 'erp.nas.root_help_server'
                    }))
                    ->placeholder('/ERP')
                    ->default('/ERP')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->label(__('erp.nas.username'))
                    ->helperText(__('erp.nas.username_help'))
                    ->required()
                    ->maxLength(100),
                // Empty keeps the current one; never shown again.
                TextInput::make('password')
                    ->label(__('erp.nas.password'))
                    ->helperText(fn (?NasTarget $record): string => filled($record?->password) ? __('erp.nas.password_saved') : __('erp.nas.password_help'))
                    ->password()
                    ->revealable()
                    ->formatStateUsing(fn (): ?string => null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Textarea::make('private_key')
                    ->label(__('erp.nas.private_key'))
                    ->helperText(fn (?NasTarget $record): string => filled($record?->private_key) ? __('erp.nas.private_key_saved') : __('erp.nas.private_key_help'))
                    ->rows(3)
                    ->formatStateUsing(fn (): ?string => null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::SFTP)
                    ->columnSpanFull(),
                Toggle::make('ftps')->label(__('erp.nas.ftps'))->helperText(__('erp.nas.ftps_help'))->default(true)
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::FTP),
                Toggle::make('passive')->label(__('erp.nas.passive'))->default(true)
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::FTP),
                Toggle::make('verify_ssl')->label(__('erp.nas.verify_ssl'))->helperText(__('erp.nas.verify_ssl_help'))->default(true)
                    ->visible(fn (Get $get): bool => $driver($get) === NasTransports::SYNOLOGY)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $count = fn (string $status) => fn (NasTarget $record): int => $record->items()->where('status', $status)->count();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withMax('items', 'synced_at'))
            ->description(__('erp.nas.help'))
            ->emptyStateHeading(__('erp.nas.none'))
            ->emptyStateDescription(__('erp.nas.none_help'))
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight('bold')->description(fn (NasTarget $record): string => $record->address())->searchable(),
                TextColumn::make('driver')->label(__('erp.nas.driver'))->formatStateUsing(fn (string $state): string => __("erp.nas.drivers.{$state}"))->badge()->color('gray'),
                TextColumn::make('root')->label(__('erp.nas.root'))->fontFamily('mono'),
                IconColumn::make('is_active')->label(__('erp.nas.active'))->boolean(),
                TextColumn::make('waiting')->label(__('erp.nas.waiting'))->state($count(NasSyncItem::PENDING))->badge()->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('failed')->label(__('erp.nas.failed'))->state($count(NasSyncItem::FAILED))->badge()->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->tooltip(fn (NasTarget $record): ?string => $record->items()->where('status', NasSyncItem::FAILED)->latest('updated_at')->value('last_error')),
                TextColumn::make('copied')->label(__('erp.nas.copied'))->state($count(NasSyncItem::DONE))->badge()->color('success'),
                TextColumn::make('items_max_synced_at')->label(__('erp.nas.last_copied'))->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label(__('erp.nas.test'))
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('gray')
                    ->action(function (NasTarget $record): void {
                        try {
                            $transport = NasTransports::for($record);
                            $found = $transport->test();
                            $transport->close();
                            Notification::make()->title(__('erp.nas.test_ok'))->body($found)->success()->send();
                        } catch (Throwable $exception) {
                            Notification::make()->title(__('erp.nas.test_failed'))->body($exception->getMessage())->danger()->persistent()->send();
                        }
                    }),
                ActionGroup::make([
                    Action::make('syncAll')
                        ->label(__('erp.nas.sync_all'))
                        ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                        ->requiresConfirmation()
                        ->modalDescription(__('erp.nas.sync_all_help'))
                        ->visible(fn (NasTarget $record): bool => $record->is_active)
                        ->action(function (NasTarget $record, NasSync $nas): void {
                            Notification::make()->title(__('erp.nas.sync_all_queued', ['count' => $nas->queueAll($record)]))->success()->send();
                        }),
                    Action::make('retry')
                        ->label(__('erp.nas.retry'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (NasTarget $record): bool => $record->items()->where('status', NasSyncItem::FAILED)->exists())
                        ->action(function (NasTarget $record): void {
                            $record->items()->where('status', NasSyncItem::FAILED)->update(['attempts' => 0]);
                            Notification::make()->title(__('erp.nas.retry_queued'))->success()->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make()->modalDescription(__('erp.nas.delete_help')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNasTargets::route('/'),
        ];
    }
}
