<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Enums\ProjectStatus;
use App\Http\Controllers\PeppolInboundController;
use App\Models\Client;
use App\Models\Employee;
use App\Support\Modules;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $values['finance']['peppol_api_key'] = null;

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
                        // Only while the finance module is on.
                        Tab::make(__('erp.settings.tabs.finance'))
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->visible(fn (): bool => modules()->finance())
                            ->schema($this->financeFields()),
                        // Only while the prestations module is on.
                        Tab::make(__('erp.settings.tabs.timesheets'))
                            ->icon(Heroicon::OutlinedClock)
                            ->visible(fn (): bool => modules()->timesheets())
                            ->schema($this->timesheetFields()),
                        // Only while the batteries module is on.
                        Tab::make(__('erp.settings.tabs.batteries'))
                            ->icon(Heroicon::OutlinedBattery100)
                            ->visible(fn (): bool => modules()->batteries())
                            ->schema($this->batteryFields()),
                        // Only while the planning module is on.
                        Tab::make(__('erp.settings.tabs.planning'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->visible(fn (): bool => modules()->planning())
                            ->schema($this->planningFields()),
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

        foreach (['appearance.logo', 'appearance.favicon', 'batteries.background', 'batteries.inspection_header'] as $image) {
            $old = $settings->get($image);

            if (filled($old) && Arr::has($data, $image) && $old !== Arr::get($data, $image)) {
                Storage::disk('public')->delete($old);
            }
        }

        if (isset($data['finance'])) {
            $data['finance']['peppol_api_key'] = filled($data['finance']['peppol_api_key'] ?? null)
                ? Crypt::encryptString($data['finance']['peppol_api_key'])
                : $settings->get('finance.peppol_api_key');
            $data['finance']['peppol_inbound_secret'] = $settings->get('finance.peppol_inbound_secret') ?: Str::random(40);
            $data['finance']['vat_number'] = Client::normaliseVat($data['finance']['vat_number'] ?? null);
            $data['finance']['iban'] = filled($data['finance']['iban'] ?? null) ? strtoupper(preg_replace('/\s+/', '', $data['finance']['iban'])) : null;
        }

        if (isset($data['timesheets'])) {
            $data['timesheets']['send_day'] = (int) $data['timesheets']['send_day'];
            $data['timesheets']['mobility_rate'] = (float) $data['timesheets']['mobility_rate'];
            $data['timesheets']['send_time'] = substr((string) $data['timesheets']['send_time'], 0, 5);
            $data['timesheets']['recipients'] = array_values(array_unique(array_map('strtolower', $data['timesheets']['recipients'] ?? [])));
        }

        if (isset($data['planning'])) {
            $data['planning']['slots'] = array_values(array_map(fn (array $slot): array => [
                'label' => $slot['label'],
                'start' => substr((string) $slot['start'], 0, 5),
                'end' => substr((string) $slot['end'], 0, 5),
            ], $data['planning']['slots'] ?? []));
            $data['planning']['notify_emails'] = array_values(array_unique(array_map('strtolower', $data['planning']['notify_emails'] ?? [])));
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
        $this->data['finance']['peppol_api_key'] = null;

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
            Modules::FINANCE => Heroicon::OutlinedBanknotes,
            Modules::PLANNING => Heroicon::OutlinedCalendarDays,
            Modules::INTERVENTIONS => Heroicon::OutlinedWrench,
            Modules::BATTERIES => Heroicon::OutlinedBattery100,
            Modules::TIMESHEETS => Heroicon::OutlinedClock,
            Modules::CHARGING => Heroicon::OutlinedBolt,
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
    /**
     * Company details printed on invoices (and used for Peppol), numbering, payment reminders and
     * the Peppol access point.
     *
     * @return array<Component|Field>
     */
    protected function financeFields(): array
    {
        return [
            Section::make(__('erp.settings.sections.finance_company'))
                ->description(__('erp.settings.help.finance_company'))
                ->columns(6)
                ->schema([
                    TextInput::make('finance.company_name')->label(__('erp.settings.fields.legal_name'))->maxLength(255)->columnSpan(3),
                    TextInput::make('finance.vat_number')
                        ->label(__('erp.settings.fields.company_vat'))
                        ->helperText(__('erp.settings.help.company_vat'))
                        ->placeholder('BE0123.456.789')
                        ->maxLength(20)
                        ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (filled($value) && ! Client::isValidVat((string) $value)) {
                                $fail(__('erp.finance.vat_invalid'));
                            }
                        })
                        ->columnSpan(3),
                    TextInput::make('finance.street')->label(__('erp.fields.street_only'))->maxLength(150)->columnSpan(4),
                    TextInput::make('finance.house_number')->label(__('erp.fields.house_number'))->maxLength(20)->columnSpan(2),
                    TextInput::make('finance.postal_code')->label(__('erp.fields.postal_code'))->maxLength(20)->columnSpan(2),
                    TextInput::make('finance.city')->label(__('erp.fields.city'))->maxLength(100)->columnSpan(2),
                    TextInput::make('finance.country_code')->label(__('erp.settings.fields.country_code'))->maxLength(2)->default('BE')->columnSpan(2),
                    TextInput::make('finance.email')->label(__('erp.fields.email'))->email()->maxLength(255)->columnSpan(3),
                    TextInput::make('finance.phone')->label(__('erp.fields.phone'))->maxLength(50)->columnSpan(3),
                    TextInput::make('finance.iban')
                        ->label('IBAN')
                        ->placeholder('BE68 5390 0754 7034')
                        ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (filled($value) && ! Employee::isValidIban((string) $value)) {
                                $fail(__('erp.employees.bank_account_invalid'));
                            }
                        })
                        ->columnSpan(3),
                    TextInput::make('finance.bic')->label('BIC')->maxLength(11)->columnSpan(3),
                    Textarea::make('finance.invoice_footer')->label(__('erp.settings.fields.invoice_footer'))->helperText(__('erp.settings.help.invoice_footer'))->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
            Section::make(__('erp.settings.sections.finance_numbering'))
                ->columns(3)
                ->schema([
                    TextInput::make('finance.invoice_prefix')->label(__('erp.settings.fields.invoice_prefix'))->helperText(__('erp.settings.help.invoice_prefix'))->maxLength(10),
                    TextInput::make('finance.credit_note_prefix')->label(__('erp.settings.fields.credit_note_prefix'))->maxLength(10),
                    TextInput::make('finance.payment_term_days')->label(__('erp.settings.fields.payment_term'))->helperText(__('erp.settings.help.payment_term'))->integer()->minValue(0)->maxValue(365)->suffix(__('erp.vacations.days')),
                ]),
            Section::make(__('erp.settings.sections.finance_reminders'))
                ->description(__('erp.settings.help.finance_reminders'))
                ->columns(3)
                ->schema([
                    Toggle::make('finance.reminders_enabled')->label(__('erp.settings.fields.reminders_enabled'))->columnSpanFull(),
                    TextInput::make('finance.reminder_1_days')->label(__('erp.settings.fields.reminder_days', ['level' => 1]))->integer()->minValue(1)->maxValue(365)->suffix(__('erp.vacations.days')),
                    TextInput::make('finance.reminder_2_days')->label(__('erp.settings.fields.reminder_days', ['level' => 2]))->integer()->minValue(1)->maxValue(365)->suffix(__('erp.vacations.days')),
                    TextInput::make('finance.reminder_3_days')->label(__('erp.settings.fields.reminder_days', ['level' => 3]))->integer()->minValue(1)->maxValue(365)->suffix(__('erp.vacations.days')),
                ]),
            Section::make('Peppol')
                ->description(__('erp.settings.help.peppol'))
                ->columns(2)
                ->schema([
                    Toggle::make('finance.peppol_enabled')->label(__('erp.settings.fields.peppol_enabled'))->live()->columnSpanFull(),
                    TextInput::make('finance.peppol_id')
                        ->label(__('erp.settings.fields.peppol_id'))
                        ->helperText(__('erp.settings.help.peppol_id'))
                        ->placeholder('0208:0123456789')
                        ->regex('/^\d{4}:[A-Za-z0-9]+$/'),
                    TextInput::make('finance.peppol_provider_url')
                        ->label(__('erp.settings.fields.peppol_provider_url'))
                        ->helperText(__('erp.settings.help.peppol_provider_url'))
                        ->url(),
                    TextInput::make('finance.peppol_api_key')
                        ->label(__('erp.settings.fields.peppol_api_key'))
                        ->helperText(__('erp.help.password_keep'))
                        ->password()
                        ->revealable(),
                    Text::make(fn (): string => __('erp.settings.help.peppol_inbound', ['url' => PeppolInboundController::url() ?? __('erp.settings.help.peppol_inbound_after_save')]))
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function timesheetFields(): array
    {
        $days = collect(range(1, 7))->mapWithKeys(fn (int $day): array => [$day => now()->startOfWeek()->addDays($day - 1)->locale(app()->getLocale())->translatedFormat('l')])->all();

        return [
            Section::make(__('erp.settings.sections.timesheet_hours'))
                ->columns(3)
                ->schema([
                    TextInput::make('timesheets.expected_hours')->label(__('erp.settings.fields.timesheet_expected_hours'))->numeric()->minValue(0)->maxValue(80)->step('any')->suffix('u')->required(),
                    TextInput::make('timesheets.day_hours')->label(__('erp.settings.fields.timesheet_day_hours'))->helperText(__('erp.settings.help.timesheet_day_hours'))->numeric()->minValue(0)->maxValue(24)->step('any')->suffix('u')->required(),
                    TextInput::make('timesheets.lunch_hours')->label(__('erp.settings.fields.timesheet_lunch_hours'))->numeric()->minValue(0)->maxValue(4)->step('any')->suffix('u')->required(),
                    TextInput::make('timesheets.mobility_rate')->label(__('erp.settings.fields.timesheet_mobility_rate'))->helperText(__('erp.settings.help.timesheet_mobility_rate'))->numeric()->minValue(0)->maxValue(5)->step('any')->prefix('€')->suffix('/ km')->required(),
                ]),
            Section::make(__('erp.settings.sections.timesheet_mail'))
                ->description(__('erp.settings.help.timesheet_mail'))
                ->columns(3)
                ->schema([
                    Toggle::make('timesheets.auto_send')->label(__('erp.settings.fields.timesheet_auto_send'))->columnSpanFull(),
                    Select::make('timesheets.send_day')->label(__('erp.settings.fields.timesheet_send_day'))->options($days)->required(),
                    TextInput::make('timesheets.send_time')->label(__('erp.settings.fields.timesheet_send_time'))->type('time')->required(),
                    TagsInput::make('timesheets.recipients')
                        ->label(__('erp.settings.fields.timesheet_recipients'))
                        ->placeholder('payroll@example.com')
                        ->nestedRecursiveRules(['email:rfc', 'max:255'])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function batteryFields(): array
    {
        $template = fn (string $key): FileUpload => FileUpload::make("batteries.{$key}")
            ->label(__("erp.settings.fields.battery_{$key}"))
            ->helperText(__("erp.settings.help.battery_{$key}"))
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg'])
            ->maxSize(8192)
            ->disk('public')
            ->directory('battery-templates')
            ->visibility('public')
            ->imagePreviewHeight('160');
        $money = fn (string $key): TextInput => TextInput::make("batteries.{$key}")
            ->label(__("erp.settings.fields.battery_{$key}"))
            ->numeric()
            ->minValue(0)
            ->step('any')
            ->prefix('€')
            ->required();
        $text = fn (string $key): TextInput => TextInput::make("batteries.{$key}")->label(__("erp.settings.fields.battery_{$key}"))->maxLength(255);
        $lines = fn (string $key, int $rows = 3): Textarea => Textarea::make("batteries.{$key}")->label(__("erp.settings.fields.battery_{$key}"))->rows($rows)->maxLength(2000);

        return [
            Section::make(__('erp.settings.sections.battery_templates'))
                ->description(__('erp.settings.help.battery_templates'))
                ->columns(2)
                ->schema([
                    $template('background'),
                    $template('inspection_header'),
                ]),
            Section::make(__('erp.settings.sections.battery_client'))
                ->columns(3)
                ->schema([
                    $text('client_name')->required(),
                    $text('client_short')->required(),
                    $text('client_vat'),
                    $text('framework_contract'),
                    $text('brand'),
                    $text('supplier_location'),
                    TextInput::make('batteries.project_category')
                        ->label(__('erp.settings.fields.battery_project_category'))
                        ->helperText(__('erp.settings.help.battery_project_category'))
                        ->required()
                        ->maxLength(6),
                    Select::make('batteries.delivered_project_status')
                        ->label(__('erp.settings.fields.battery_delivered_project_status'))
                        ->helperText(__('erp.settings.help.battery_delivered_project_status'))
                        ->options(ProjectStatus::class)
                        ->placeholder(__('erp.settings.fields.battery_status_unchanged')),
                    TagsInput::make('batteries.contacts')->label(__('erp.settings.fields.battery_contacts')),
                    TagsInput::make('batteries.waste_types')->label(__('erp.settings.fields.battery_waste_types'))->columnSpanFull(),
                ]),
            Section::make(__('erp.settings.sections.battery_prices'))
                ->description(__('erp.settings.help.battery_prices'))
                ->columns(4)
                ->schema([
                    $money('hourly_rate'), $money('vertical_transport_price'), $money('ppe_set_price'), $money('maintenance_set_price'),
                    $money('load_plate_price'), $money('lug_price'), $money('tray_price'), $money('doc_holder_price'), $money('picto_price'),
                    $money('removal_base_opzv'), $money('removal_base_other'),
                    TextInput::make('batteries.removal_factor_opzv')->label(__('erp.settings.fields.battery_removal_factor_opzv'))->numeric()->step('any')->required(),
                    TextInput::make('batteries.removal_factor_other')->label(__('erp.settings.fields.battery_removal_factor_other'))->numeric()->step('any')->required(),
                ]),
            Section::make(__('erp.settings.sections.battery_documents'))
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    $lines('company_block', 4),
                    $lines('seat_block', 4),
                    $text('shipping_carrier'),
                    $text('shipping_collector'),
                    $lines('shipping_sender')->helperText(__('erp.settings.help.battery_shipping_sender')),
                    $lines('shipping_transporter'),
                    $lines('shipping_transporter_signature'),
                    $lines('shipping_registrations')->helperText(__('erp.settings.help.battery_shipping_registrations')),
                    $lines('shipping_destination'),
                    $text('shipping_destination_registration'),
                    $text('shipping_description'),
                    $text('shipping_physical_state'),
                    $text('shipping_packaging'),
                    $text('shipping_eural'),
                    $text('shipping_rd_code'),
                    $lines('shipping_adr', 2)->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function planningFields(): array
    {
        return [
            Section::make(__('erp.settings.sections.planning_slots'))
                ->description(__('erp.settings.help.planning_slots'))
                ->schema([
                    Repeater::make('planning.slots')
                        ->hiddenLabel()
                        ->table([
                            Repeater\TableColumn::make(__('erp.fields.name')),
                            Repeater\TableColumn::make(__('erp.planning.start')),
                            Repeater\TableColumn::make(__('erp.planning.end')),
                        ])
                        ->schema([
                            TextInput::make('label')->required()->maxLength(40),
                            TimePicker::make('start')->seconds(false)->required(),
                            TimePicker::make('end')->seconds(false)->required()->after('start'),
                        ])
                        ->reorderable()
                        ->maxItems(12)
                        ->addActionLabel(__('erp.settings.fields.add_slot')),
                ]),
            Section::make(__('erp.settings.sections.planning_mail'))
                ->description(__('erp.settings.help.planning_mail'))
                ->schema([
                    Toggle::make('planning.mail_default')->label(__('erp.settings.fields.planning_mail_default')),
                    TagsInput::make('planning.notify_emails')
                        ->label(__('erp.settings.fields.planning_notify_emails'))
                        ->helperText(__('erp.settings.help.planning_notify_emails'))
                        ->placeholder('planning@example.com')
                        ->nestedRecursiveRules(['email:rfc', 'max:255']),
                ]),
        ];
    }

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
