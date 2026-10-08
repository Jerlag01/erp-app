<?php

namespace App\Filament\App\Pages;

use App\Actions\SubmitSuggestion as SubmitSuggestionAction;
use App\Enums\SuggestionType;
use App\Models\Suggestion;
use App\Support\PrivatePhotos;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The idea/complaint box form. It is reached through the link next to the search bar,
 * so it is not repeated in the sidebar.
 *
 * @property-read Schema $form
 */
class SubmitSuggestion extends Page
{
    protected static ?string $slug = 'idea-box';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && Gate::allows('create', Suggestion::class);
    }

    public function getTitle(): string
    {
        return __('erp.suggestions.title');
    }

    public function getSubheading(): ?string
    {
        return __('erp.suggestions.intro');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->schema([
                        ToggleButtons::make('type')
                            ->label(__('erp.suggestions.type'))
                            ->options(SuggestionType::class)
                            ->icons([
                                SuggestionType::Idea->value => 'heroicon-o-light-bulb',
                                SuggestionType::Complaint->value => 'heroicon-o-chat-bubble-left-ellipsis',
                                SuggestionType::Other->value => 'heroicon-o-ellipsis-horizontal-circle',
                            ])
                            ->inline()
                            ->required(),
                        Textarea::make('description')
                            ->label(__('erp.suggestions.description'))
                            ->placeholder(__('erp.suggestions.description_placeholder'))
                            ->rows(7)
                            ->required()
                            ->maxLength(10000),
                        PrivatePhotos::upload('photos', 'suggestions')
                            ->label(__('erp.incidents.photos'))
                            ->helperText(__('erp.incidents.photos_help')),
                        Toggle::make('may_be_public')
                            ->label(__('erp.suggestions.may_be_public'))
                            ->helperText(__('erp.suggestions.may_be_public_help')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('submit')
                    ->footer([
                        Actions::make([
                            Action::make('submit')
                                ->label(__('erp.suggestions.submit'))
                                ->icon(Heroicon::OutlinedPaperAirplane)
                                ->submit('submit'),
                        ]),
                    ]),
            ]);
    }

    public function submit(): void
    {
        app(SubmitSuggestionAction::class)->handle(auth()->user(), $this->form->getState());

        Notification::make()
            ->title(__('erp.suggestions.thanks'))
            ->success()
            ->send();

        $this->form->fill();
    }
}
