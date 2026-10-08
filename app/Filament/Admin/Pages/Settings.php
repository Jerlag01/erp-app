<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Support\Modules;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.settings.title');
    }

    public function getTitle(): string
    {
        return __('erp.settings.title');
    }

    public function mount(SiteSettings $settings): void
    {
        $values = $settings->all();
        $values['mail']['password'] = null;

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('erp.settings.tabs.general'))
                            ->icon(Heroicon::OutlinedHome)
                            ->schema($this->generalFields()),
                        Tab::make(__('erp.settings.tabs.modules'))
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->schema($this->moduleFields()),
                        Tab::make(__('erp.settings.tabs.mail'))
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema($this->mailFields()),
                        Tab::make(__('erp.settings.tabs.appearance'))
                            ->icon(Heroicon::OutlinedSwatch)
                            ->schema($this->appearanceFields()),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('erp.actions.save'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(SiteSettings $settings): void
    {
        $data = $this->form->getState();

        $data['mail']['password'] = filled($data['mail']['password'] ?? null)
            ? Crypt::encryptString($data['mail']['password'])
            : $settings->get('mail.password');

        foreach (['logo', 'favicon'] as $image) {
            $old = $settings->get("appearance.{$image}");

            if (filled($old) && $old !== ($data['appearance'][$image] ?? null)) {
                Storage::disk('public')->delete($old);
            }
        }

        $data['modules'] = collect(Modules::ALL)
            ->mapWithKeys(fn (string $module): array => [$module => (bool) ($data['modules'][$module] ?? false)])
            ->all();

        foreach (SiteSettings::GROUPS as $group) {
            if (isset($data[$group])) {
                $settings->save($group, $data[$group]);
            }
        }

        $this->data['mail']['password'] = null;

        Notification::make()
            ->title(__('erp.settings.saved'))
            ->success()
            ->send();

        // Navigation, colours and the logo come from the settings: reload to show the changes.
        $this->redirect(static::getUrl());
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestMail')
                ->label(__('erp.settings.test_mail'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.settings.test_mail_description', ['email' => auth()->user()->email]))
                ->action(function (SiteSettings $settings): void {
                    $settings->applyMailConfig();
                    Mail::purge();

                    try {
                        Mail::raw(__('erp.settings.test_mail_body', ['site' => $settings->siteName()]), function ($message) use ($settings): void {
                            $message->to(auth()->user()->email)->subject($settings->siteName().' - test');
                        });

                        Notification::make()->title(__('erp.settings.test_mail_sent'))->success()->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('erp.settings.test_mail_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<Component|Field>
     */
    protected function generalFields(): array
    {
        return [
            Section::make(__('erp.settings.sections.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('general.site_name')
                        ->label(__('erp.settings.fields.site_name'))
                        ->helperText(__('erp.settings.help.site_name'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('general.company_name')
                        ->label(__('erp.settings.fields.company_name'))
                        ->maxLength(150),
                ]),
            Section::make(__('erp.settings.sections.reminders'))
                ->schema([
                    TextInput::make('general.warning_days')
                        ->label(__('erp.settings.fields.warning_days'))
                        ->helperText(__('erp.settings.help.warning_days'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->suffix(__('erp.settings.fields.days'))
                        ->required(),
                    TextInput::make('general.it_audit_months')
                        ->label(__('erp.settings.fields.it_audit_months'))
                        ->helperText(__('erp.settings.help.it_audit_months'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60)
                        ->suffix(__('erp.fields.months'))
                        ->visible(fn (): bool => modules()->it())
                        ->required(),
                ]),
        ];
    }

    /**
     * @return array<Component|Field>
     */
    protected function moduleFields(): array
    {
        $icons = [
            Modules::FLEET => Heroicon::OutlinedTruck,
            Modules::TEAMS => Heroicon::OutlinedUserGroup,
            Modules::ASSETS => Heroicon::OutlinedBolt,
            Modules::VACATIONS => Heroicon::OutlinedSun,
            Modules::ORDERS => Heroicon::OutlinedHashtag,
            Modules::LOCATIONS => Heroicon::OutlinedMapPin,
            Modules::STOCK => Heroicon::OutlinedArchiveBox,
            Modules::IT => Heroicon::OutlinedComputerDesktop,
            Modules::WORK_SITES => Heroicon::OutlinedBuildingOffice,
            Modules::INCIDENTS => Heroicon::OutlinedExclamationTriangle,
            Modules::SUGGESTIONS => Heroicon::OutlinedLightBulb,
            Modules::PROJECTS => Heroicon::OutlinedBriefcase,
            Modules::KNOWLEDGE_BASE => Heroicon::OutlinedBookOpen,
            Modules::EMPLOYEES => Heroicon::OutlinedIdentification,
            Modules::WASTE => Heroicon::OutlinedTrash,
        ];

        return [
            Section::make(__('erp.settings.sections.modules'))
                ->description(__('erp.settings.help.modules'))
                ->schema(collect(Modules::ALL)
                    ->map(fn (string $module): Toggle => Toggle::make("modules.{$module}")
                        ->label(__("erp.modules.{$module}.label"))
                        ->helperText(__("erp.modules.{$module}.help"))
                        ->onIcon($icons[$module])
                        ->onColor('success')
                        ->live())
                    ->values()
                    ->all()),
            Section::make(__('erp.settings.sections.links'))
                ->description(__('erp.settings.help.links'))
                ->schema([
                    Text::make(fn (Get $get): string => collect([
                        [Modules::TEAMS, Modules::FLEET, 'teams_fleet'],
                        [Modules::ASSETS, Modules::TEAMS, 'assets_teams'],
                        [Modules::ASSETS, Modules::FLEET, 'assets_fleet'],
                    ])->map(fn (array $link): string => ($get("modules.{$link[0]}") && $get("modules.{$link[1]}") ? '✓ ' : '✗ ').__("erp.settings.links.{$link[2]}"))
                        ->implode(' · ')),
                ]),
        ];
    }

    /**
     * @return array<Component|Field>
     */
    protected function mailFields(): array
    {
        $usesSmtp = fn (Get $get): bool => $get('mail.mailer') === 'smtp';

        return [
            Section::make(__('erp.settings.sections.mail'))
                ->description(__('erp.settings.help.mail'))
                ->columns(2)
                ->schema([
                    Select::make('mail.mailer')
                        ->label(__('erp.settings.fields.mailer'))
                        ->options([
                            'smtp' => 'SMTP',
                            'sendmail' => 'Sendmail',
                            'log' => __('erp.settings.mailers.log'),
                        ])
                        ->placeholder(__('erp.settings.mailers.env'))
                        ->live(),
                    TextInput::make('mail.host')
                        ->label(__('erp.settings.fields.host'))
                        ->placeholder('smtp.example.com')
                        ->required($usesSmtp)
                        ->visible($usesSmtp),
                    TextInput::make('mail.port')
                        ->label(__('erp.settings.fields.port'))
                        ->numeric()
                        ->default(587)
                        ->required($usesSmtp)
                        ->visible($usesSmtp),
                    Select::make('mail.scheme')
                        ->label(__('erp.settings.fields.scheme'))
                        ->options([
                            'smtp' => 'STARTTLS (587)',
                            'smtps' => 'SSL/TLS (465)',
                        ])
                        ->placeholder(__('erp.settings.fields.scheme_auto'))
                        ->visible($usesSmtp),
                    TextInput::make('mail.username')
                        ->label(__('erp.settings.fields.username'))
                        ->autocomplete('off')
                        ->visible($usesSmtp),
                    TextInput::make('mail.password')
                        ->label(__('erp.fields.password'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->helperText(__('erp.settings.help.mail_password'))
                        ->visible($usesSmtp),
                    TextInput::make('mail.from_address')
                        ->label(__('erp.settings.fields.from_address'))
                        ->email(),
                    TextInput::make('mail.from_name')
                        ->label(__('erp.settings.fields.from_name'))
                        ->placeholder(fn (): string => settings()->siteName()),
                ]),
        ];
    }

    /**
     * @return array<Component|Field>
     */
    protected function appearanceFields(): array
    {
        return [
            Section::make(__('erp.settings.sections.colors'))
                ->description(__('erp.settings.help.colors'))
                ->columns(2)
                ->schema([
                    ColorPicker::make('appearance.primary')
                        ->label(__('erp.settings.fields.primary'))
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->required(),
                    ColorPicker::make('appearance.accent')
                        ->label(__('erp.settings.fields.accent'))
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->required(),
                ]),
            Section::make(__('erp.settings.sections.logo'))
                ->description(__('erp.settings.help.logo'))
                ->schema([
                    // No SVG uploads: an SVG on the public disk could carry script.
                    FileUpload::make('appearance.logo')
                        ->hiddenLabel()
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                        ->maxSize(1024)
                        ->disk('public')
                        ->directory('branding')
                        ->visibility('public')
                        ->imagePreviewHeight('80'),
                ]),
            Section::make(__('erp.settings.sections.favicon'))
                ->description(__('erp.settings.help.favicon'))
                ->schema([
                    FileUpload::make('appearance.favicon')
                        ->hiddenLabel()
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp'])
                        ->maxSize(512)
                        ->disk('public')
                        ->directory('branding')
                        ->visibility('public')
                        ->imagePreviewHeight('64'),
                ]),
        ];
    }
}
