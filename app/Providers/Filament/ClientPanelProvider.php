<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\Dashboard;
use App\Filament\Client\Pages\EditClientProfile;
use App\Http\Middleware\SetClientLocale;
use App\Support\ClientPortal;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * "Global overview for Client": the client portal on /client, with its own users (the "client" guard)
 * and only read-only overviews. Nothing of the ERP's own navigation or users is reachable from here.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(ClientPortal::PANEL)
            ->path('client')
            ->authGuard(ClientPortal::GUARD)
            ->authPasswordBroker('client_users')
            ->login()
            ->passwordReset()
            ->profile(EditClientProfile::class, isSimple: false)
            ->brandName(fn (): string => __('erp.client_portal.brand'))
            ->brandLogo(fn () => view('filament.brand', ['label' => __('erp.client_portal.brand')]))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): string => settings()->faviconUrl() ?? asset('favicon.svg'))
            ->colors(fn (): array => [
                'primary' => Color::hex(settings()->color('accent')),
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->font('Inter')
            ->topNavigation()
            ->maxContentWidth('full')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.theme'))
            // The public GDPR statement: under the sign-in form and in the user menu.
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.gdpr-link'))
            ->userMenuItems([
                Action::make('gdpr')
                    ->label(fn (): string => __('gdpr.menu'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => route('gdpr', ['lang' => app()->getLocale()]), shouldOpenInNewTab: true),
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->pages([Dashboard::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SetClientLocale::class,
            ], isPersistent: true);
    }
}
