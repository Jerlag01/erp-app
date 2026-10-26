<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Forms\SignaturePad;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSignature;
use App\Models\User;
use App\Support\Onboarding as OnboardingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * "Onboarding": the onboarding documents in the user's language. Each one is opened and then signed
 * (name and written signature, with the time) to confirm it was read. A new user sees this page first
 * until everything is signed; afterwards it stays available to look back.
 */
class Onboarding extends Page
{
    protected static ?string $slug = 'onboarding';

    protected string $view = 'filament.pages.onboarding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return modules()->onboarding() && auth()->user() instanceof User;
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.onboarding.title');
    }

    public function getTitle(): string
    {
        return __('erp.onboarding.title');
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function service(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    /**
     * @return Collection<int, OnboardingDocument>
     */
    public function documents(): Collection
    {
        return $this->service()->documentsFor($this->user());
    }

    /**
     * @return Collection<int, OnboardingSignature>
     */
    public function signatures(): Collection
    {
        return $this->service()->signaturesFor($this->user());
    }

    public function mustComplete(): bool
    {
        return $this->service()->needsOnboarding($this->user());
    }

    /**
     * Opening a document (the link opens it in a new tab) makes signing possible: the signing window
     * is waiting when the user comes back to this tab.
     */
    public function opened(int $document): void
    {
        $found = $this->documents()->firstWhere('id', $document);
        abort_if($found === null, 404);

        if (! $this->service()->open($this->user(), $found)->isSigned()) {
            $this->mountAction('sign', ['document' => $found->id]);
        }
    }

    public function signAction(): Action
    {
        return Action::make('sign')
            ->label(__('erp.onboarding.sign'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $arguments): string => __('erp.onboarding.sign_heading', ['title' => $this->documents()->firstWhere('id', (int) ($arguments['document'] ?? 0))?->title ?? '']))
            ->modalDescription(__('erp.onboarding.sign_help'))
            ->schema([
                TextInput::make('signer_name')
                    ->label(__('erp.batteries.signature.name'))
                    ->default(fn (): string => $this->user()->name)
                    ->required()
                    ->maxLength(150),
                SignaturePad::make('signature')->label(__('erp.batteries.signature.title'))->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $document = $this->documents()->firstWhere('id', (int) ($arguments['document'] ?? 0));
                abort_if($document === null, 404);

                try {
                    $this->service()->sign($this->user(), $document, $data['signer_name'], $data['signature']);
                } catch (\DomainException) {
                    Notification::make()->title(__('erp.onboarding.open_first'))->warning()->send();

                    return;
                }

                $completed = $this->user()->refresh()->onboarding_completed_at !== null && $this->service()->pendingFor($this->user())->isEmpty();
                Notification::make()
                    ->title($completed ? __('erp.onboarding.completed') : __('erp.onboarding.signed'))
                    ->success()
                    ->send();
            });
    }
}
