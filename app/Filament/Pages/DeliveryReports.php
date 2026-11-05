<?php

namespace App\Filament\Pages;

use App\Actions\It\ItDeliveryReports;
use App\Enums\NavigationGroup;
use App\Filament\Forms\SignaturePad;
use App\Models\ItDeliveryReport;
use App\Models\SignedDocument;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The employee's own delivery reports of IT assets to sign, and their signed documents. In the menu
 * only for someone who has (or had) a report.
 */
class DeliveryReports extends Page
{
    protected static ?string $slug = 'delivery-reports';

    protected string $view = 'filament.pages.delivery-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 81;

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (ItDeliveryReport::query()->where('user_id', $user->id)->exists() || SignedDocument::query()->where('user_id', $user->id)->exists());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ItDeliveryReport::query()->where('user_id', auth()->id())->unsigned()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.it.delivery_report.page_title');
    }

    public function getTitle(): string
    {
        return __('erp.it.delivery_report.page_title');
    }

    /**
     * @return Collection<int, ItDeliveryReport>
     */
    public function toSign(): Collection
    {
        return ItDeliveryReport::query()->where('user_id', auth()->id())->unsigned()->with('asset')->latest('id')->get();
    }

    /**
     * @return Collection<int, SignedDocument>
     */
    public function signedDocuments(): Collection
    {
        return SignedDocument::query()->where('user_id', auth()->id())->latest('signed_at')->get();
    }

    public function signAction(): Action
    {
        $report = fn (array $arguments): ?ItDeliveryReport => $this->toSign()->firstWhere('id', (int) ($arguments['report'] ?? 0));

        return Action::make('sign')
            ->label(__('erp.it.delivery_report.sign'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $arguments): string => __('erp.it.delivery_report.sign_heading', ['device' => $report($arguments)?->deviceLabel() ?? '']))
            ->modalDescription(__('erp.it.delivery_report.sign_help'))
            ->schema([
                TextInput::make('signer_name')
                    ->label(__('erp.batteries.signature.name'))
                    ->default(fn (): string => auth()->user()?->employee?->fullName() ?: (string) auth()->user()?->name)
                    ->required()
                    ->maxLength(150),
                SignaturePad::make('signature')->label(__('erp.batteries.signature.title'))->required(),
                Checkbox::make('approved')->label(__('erp.it.delivery_report.approve'))->accepted(),
            ])
            ->action(function (array $data, array $arguments) use ($report): void {
                $found = $report($arguments);
                abort_if($found === null, 404);
                app(ItDeliveryReports::class)->sign($found, $data['signer_name'], $data['signature']);
                Notification::make()->title(__('erp.it.delivery_report.signed'))->success()->send();
            });
    }
}
