<?php

namespace App\Filament\Admin\Resources\ClientUsers;

use App\Actions\ClientPortal\SendClientWelcomeMail;
use App\Enums\NavigationGroup;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\WorkSite;
use App\Models\WorkSiteContact;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                        // New users get the welcome mail by default and then choose their own password.
                        Toggle::make('send_welcome_mail')
                            ->label(__('erp.client_portal.send_welcome'))
                            ->helperText(__('erp.client_portal.send_welcome_help').' '.__('erp.client_portal.mail_templates_hint'))
                            ->default(true)
                            ->live()
                            ->visibleOn('create')
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label(__('erp.client_portal.password'))
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? __('erp.client_portal.password_edit_help') : __('erp.client_portal.password_help'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->rule('min:12')
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && ! $get('send_welcome_mail'))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.active'))
                            ->helperText(__('erp.client_portal.active_help'))
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make(__('erp.client_portal.own_projects'))
                    ->description(__('erp.client_portal.own_projects_help'))
                    ->columns(2)
                    ->schema([
                        Select::make('work_site_contact_id')
                            ->label(__('erp.client_portal.contact'))
                            ->helperText(fn (?int $state): string => self::contactSitesText($state))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::contactOptions($search))
                            ->getOptionLabelUsing(fn (int|string|null $value): ?string => WorkSiteContact::query()->find($value)?->label())
                            ->live()
                            ->afterStateUpdated(function (?int $state, Get $get, Set $set): void {
                                // A new user takes over the contact's details where nothing is filled in yet.
                                $contact = WorkSiteContact::query()->find($state);

                                if ($contact === null) {
                                    return;
                                }

                                foreach (['name' => $contact->fullName(), 'email' => $contact->email, 'company' => $contact->company] as $field => $value) {
                                    if (blank($get($field)) && filled($value)) {
                                        $set($field, $value);
                                    }
                                }
                            }),
                        Select::make('projects')
                            ->label(__('erp.client_portal.assigned_projects'))
                            ->helperText(__('erp.client_portal.assigned_projects_help'))
                            ->relationship('projects', 'reference', fn (Builder $query) => $query->whereHas('category', fn (Builder $query) => $query->whereIn('code', ClientPortal::categories())))
                            ->getOptionLabelFromRecordUsing(fn (Project $record): string => trim($record->reference.' '.($record->subtitle() ? '· '.$record->subtitle() : '')))
                            ->multiple()
                            ->searchable(['reference', 'short_description', 'work_order_number']),
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
                            ->options(fn (): array => ClientPortal::categoryOptions())
                            ->default(fn (): array => ClientPortal::categories())
                            ->columns(['default' => 2, 'md' => 4])
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
                TextColumn::make('contact.first_name')
                    ->label(__('erp.client_portal.contact'))
                    ->formatStateUsing(fn (ClientUser $record): ?string => $record->contact?->label())
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
                TextColumn::make('last_login_at')->label(__('erp.client_portal.last_login'))->since()->placeholder(__('erp.client_portal.never_signed_in'))->sortable(),
                TextColumn::make('welcome_sent_at')->label(__('erp.client_portal.welcome_sent_at'))->since()->placeholder(__('erp.client_portal.welcome_not_sent'))->sortable()->toggleable(),
            ])
            ->recordActions([
                Action::make('send_welcome')
                    ->label(__('erp.client_portal.resend_welcome'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->visible(fn (ClientUser $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription(fn (ClientUser $record): string => __('erp.client_portal.resend_welcome_help', ['email' => $record->email]))
                    ->action(fn (ClientUser $record) => self::notifyWelcome($record, app(SendClientWelcomeMail::class)->handle($record))),
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

    /**
     * Contact persons matching the search on name, company, e-mail or the COW code of one of their sites.
     *
     * @return array<int, string>
     */
    public static function contactOptions(string $search): array
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return WorkSiteContact::query()
            ->where(fn (Builder $query) => $query
                ->whereRaw("concat_ws(' ', first_name, last_name) like ?", [$like])
                ->orWhere('company', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('workSites', fn (Builder $query) => $query->where('cow_code', 'like', $like))
                ->orWhereHas('areas.workSites', fn (Builder $query) => $query->where('cow_code', 'like', $like)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (WorkSiteContact $contact): array => [$contact->id => $contact->label()])
            ->all();
    }

    /**
     * Which COW sites the chosen contact person is the contact for, as a hint under the field.
     */
    public static function contactSitesText(?int $contactId): string
    {
        if ($contactId === null) {
            return __('erp.client_portal.contact_help');
        }

        $codes = WorkSite::query()
            ->whereIn('id', (new ClientUser(['work_site_contact_id' => $contactId]))->contactWorkSiteIds())
            ->orderBy('cow_code')
            ->pluck('cow_code');

        return $codes->isEmpty()
            ? __('erp.client_portal.contact_no_sites')
            : __('erp.client_portal.contact_sites', ['count' => $codes->count(), 'codes' => $codes->take(20)->implode(', ').($codes->count() > 20 ? ', …' : '')]);
    }

    /**
     * Tells the administrator whether the welcome mail went out.
     */
    public static function notifyWelcome(ClientUser $user, bool $sent): void
    {
        $sent
            ? Notification::make()->title(__('erp.client_portal.welcome_sent', ['email' => $user->email]))->success()->send()
            : Notification::make()->title(__('erp.client_portal.welcome_failed'))->body(__('erp.client_portal.welcome_failed_body'))->warning()->persistent()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClientUsers::route('/'),
        ];
    }
}
