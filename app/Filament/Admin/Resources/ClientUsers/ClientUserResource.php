<?php

namespace App\Filament\Admin\Resources\ClientUsers;

use App\Enums\NavigationGroup;
use App\Models\ClientUser;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
use Illuminate\Support\Facades\Password;
use UnitEnum;

/**
 * The users of the client portal ("Global overview for Client", /client), e.g. Proximus managers: their
 * own accounts, and per user what they may see.
 */
class ClientUserResource extends Resource
{
    protected static ?string $model = ClientUser::class;

    protected static ?string $slug = 'client-users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 20;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.users');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.users');
    }

    /**
     * @return array<string, string>
     */
    public static function permissionOptions(): array
    {
        return collect(ClientPortal::PERMISSIONS)->mapWithKeys(fn (string $permission): array => [$permission => __("erp.client_portal.permissions.{$permission}")])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('erp.client_portal.account'))
                    ->description(fn (): string => __('erp.client_portal.account_help', ['url' => url('/client')]))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('erp.fields.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('company')->label(__('erp.client_portal.company'))->default('Proximus')->maxLength(255),
                        Select::make('locale')
                            ->label(__('erp.client_portal.language'))
                            ->options(['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'])
                            ->default('nl')
                            ->required(),
                        TextInput::make('password')
                            ->label(__('erp.client_portal.password'))
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? __('erp.client_portal.password_edit_help') : __('erp.client_portal.password_help'))
                            ->password()
                            ->revealable()
                            ->rule('min:12')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.active'))
                            ->helperText(__('erp.client_portal.active_help'))
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make(__('erp.client_portal.rights'))
                    ->description(__('erp.client_portal.rights_help'))
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label(__('erp.client_portal.may_see'))
                            ->options(self::permissionOptions())
                            ->descriptions(collect(ClientPortal::PERMISSIONS)->mapWithKeys(fn (string $permission): array => [$permission => __("erp.client_portal.permission_help.{$permission}")])->all())
                            ->default([ClientPortal::PROJECTS])
                            ->columns(2)
                            ->live(),
                        CheckboxList::make('project_categories')
                            ->label(__('erp.client_portal.categories'))
                            ->helperText(__('erp.client_portal.categories_help'))
                            ->options(array_combine(ClientPortal::CATEGORIES, ClientPortal::CATEGORIES))
                            ->default(ClientPortal::CATEGORIES)
                            ->columns(['default' => 3, 'md' => 9])
                            ->bulkToggleable()
                            ->visible(fn (Get $get): bool => in_array(ClientPortal::PROJECTS, (array) $get('permissions'), true)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading(__('erp.client_portal.no_users'))
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->searchable()->sortable()->description(fn (ClientUser $record): ?string => $record->company),
                TextColumn::make('email')->label(__('erp.fields.email'))->searchable()->copyable(),
                TextColumn::make('permissions')
                    ->label(__('erp.client_portal.may_see'))
                    ->state(fn (ClientUser $record): array => array_map(fn (string $permission): string => __("erp.client_portal.permissions.{$permission}"), (array) $record->permissions))
                    ->badge()
                    ->color(fn (string $state): string => str_contains($state, '🔒') ? 'warning' : 'gray')
                    ->wrap(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
                TextColumn::make('last_login_at')->label(__('erp.client_portal.last_login'))->since()->placeholder(__('erp.client_portal.never_signed_in'))->sortable(),
            ])
            ->recordActions([
                Action::make('send_reset')
                    ->label(__('erp.client_portal.send_reset'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ClientUser $record): string => __('erp.client_portal.send_reset_help', ['email' => $record->email]))
                    ->action(function (ClientUser $record): void {
                        $status = Password::broker('client_users')->sendResetLink(['email' => $record->email]);

                        $status === Password::RESET_LINK_SENT
                            ? Notification::make()->title(__('erp.client_portal.reset_sent'))->success()->send()
                            : Notification::make()->title(__($status))->danger()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClientUsers::route('/'),
        ];
    }
}
