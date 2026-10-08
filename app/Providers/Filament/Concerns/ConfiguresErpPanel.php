<?php

namespace App\Providers\Filament\Concerns;

use App\Enums\NavigationGroup;
use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\SetUserLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
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
 * Shared look and plumbing for the admin and employee panels, so both stay visually identical.
 */
trait ConfiguresErpPanel
{
    /**
     * The id and path come first: Filament needs the id before discovering components.
     */
    protected function configureErpPanel(Panel $panel, string $id, string $path, string $label): Panel
    {
        return $panel
            ->id($id)
            ->path($path)
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(fn (): string => settings()->siteName())
            ->brandLogo(fn () => view('filament.brand', ['label' => $label]))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): string => settings()->faviconUrl() ?? asset('favicon.svg'))
            ->colors(fn (): array => [
                'primary' => Color::hex(settings()->color('accent')),
                'gray' => Color::Slate,
                'info' => Color::Sky,
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->maxContentWidth('full')
            ->navigationGroups(NavigationGroup::navigationGroups())
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.theme'))
            ->userMenuItems([
                Action::make('employeePanel')
                    ->label(fn (): string => __('erp.panels.app'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->url('/')
                    ->visible(fn (): bool => $panel->getId() !== 'app'),
                Action::make('adminPanel')
                    ->label(fn (): string => __('erp.panels.admin'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url('/admin')
                    ->visible(fn (): bool => $panel->getId() !== 'admin' && (bool) auth()->user()?->isAdmin()),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
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
                SetUserLocale::class,
            ], isPersistent: true);
    }
}
