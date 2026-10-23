<?php

namespace App\Filament\App\Pages;

use App\Actions\RespondToSuggestion;
use App\Models\Suggestion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * The employee's own ideas and complaints with the conversation on each (?suggestion=ID): the
 * replies of the administrators and their own, with photos, and a form to reply.
 *
 * @property-read Schema $form
 */
class MySuggestions extends Page
{
    protected static ?string $slug = 'idea-box/mine';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.my-suggestions';

    #[Url]
    public ?int $suggestion = null;

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
        return __('erp.suggestions.responses.mine');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * @return Collection<int, Suggestion>
     */
    public function suggestions(): Collection
    {
        return Suggestion::query()
            ->where('user_id', auth()->id())
            ->withCount('responses')
            ->latest('submitted_at')
            ->get();
    }

    public function current(): ?Suggestion
    {
        if ($this->suggestion === null) {
            return null;
        }

        $suggestion = Suggestion::query()->find($this->suggestion);

        return $suggestion && Gate::allows('respond', $suggestion) ? $suggestion : null;
    }

    public function open(?int $id): void
    {
        $this->suggestion = $id;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(RespondToSuggestion::schema());
    }

    public function replyForm(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('reply')
                ->footer([
                    Actions::make([
                        Action::make('reply')
                            ->label(__('erp.suggestions.responses.send'))
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->submit('reply'),
                    ]),
                ]),
        ]);
    }

    public function reply(RespondToSuggestion $respond): void
    {
        $suggestion = $this->current();
        abort_if($suggestion === null, 404);

        $respond->handle($suggestion, auth()->user(), $this->form->getState());

        Notification::make()
            ->title(__('erp.suggestions.responses.sent'))
            ->body($respond->mailFailed ? __('erp.suggestions.responses.mail_failed') : null)
            ->{$respond->mailFailed ? 'warning' : 'success'}()
            ->send();

        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')
                ->label(__('erp.suggestions.responses.new'))
                ->icon(Heroicon::OutlinedLightBulb)
                ->url(SubmitSuggestion::getUrl(panel: 'app')),
        ];
    }
}
