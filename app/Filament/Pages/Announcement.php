<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Support\EmployeeBanner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The announcement for employees (module "announcements"): a short message per language, shown in
 * the top bar of the employee side left of the idea box. Edited by administrators and
 * "announcements.update".
 *
 * @property-read Schema $form
 */
class Announcement extends Page
{
    protected static ?string $slug = 'announcement';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app(EmployeeBanner::class)->canEdit(auth()->user());
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.announcements.title');
    }

    public function getTitle(): string
    {
        return __('erp.announcements.title');
    }

    public function getSubheading(): ?string
    {
        return __('erp.announcements.help');
    }

    public function mount(): void
    {
        $this->form->fill((array) settings()->get('banner'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('erp.announcements.enabled'))
                            ->live(),
                        Select::make('color')
                            ->label(__('erp.announcements.color'))
                            ->options(collect(EmployeeBanner::COLORS)->mapWithKeys(fn (string $rgb, string $color): array => [$color => __("erp.announcements.colors.{$color}")])->all())
                            ->default('info')
                            ->required()
                            ->selectablePlaceholder(false),
                        ...array_map(fn (string $locale): TextInput => TextInput::make("message_{$locale}")
                            ->label(__("erp.announcements.message_{$locale}"))
                            ->helperText($locale === 'nl' ? __('erp.announcements.message_help') : null)
                            ->required(fn (Get $get): bool => $locale === 'nl' && (bool) $get('enabled'))
                            ->maxLength(200)
                            ->columnSpanFull(), ['nl', 'fr', 'en']),
                        Text::make(fn (): string => filled($by = settings()->get('banner.updated_by_name'))
                            ? __('erp.announcements.last_change', ['name' => $by, 'date' => (string) settings()->get('banner.updated_at')])
                            : '')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('erp.announcements.save'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        settings()->save('banner', [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'color' => array_key_exists($data['color'] ?? '', EmployeeBanner::COLORS) ? $data['color'] : 'info',
            'message_nl' => $data['message_nl'] ?? null,
            'message_fr' => $data['message_fr'] ?? null,
            'message_en' => $data['message_en'] ?? null,
            'updated_by_name' => auth()->user()->name,
            'updated_at' => now()->format('d/m/Y H:i'),
        ]);

        Notification::make()->title(__('erp.announcements.saved'))->success()->send();

        // The top bar shows the new message after a reload.
        $this->redirect(static::getUrl());
    }
}
