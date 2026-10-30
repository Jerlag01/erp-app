{{--
    Suggests the app to someone who signed in on a phone (the APK, or Google Play / the App Store when the
    download is off), with the message of
    Settings > Mobile app. ✕ and "Later" hide it on this phone for 14 days; "Don't show again" keeps it
    away for this user until a new version is uploaded. Colours are set for light and dark mode
    explicitly: Filament ships precompiled CSS, so utility classes like dark:bg-… do not exist.
--}}
@php($mobileApp = app(\App\Support\MobileApp::class))
@if (auth()->check() && $mobileApp->shouldPrompt(request()->userAgent(), auth()->user()))
    @php($appLink = $mobileApp->linkFor(request()->userAgent()))
    <style>
        .erp-app-prompt { position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: center; background: rgb(15 23 42 / .6); padding: 1rem; }
        .erp-app-prompt__card { position: relative; width: 100%; max-width: 26rem; background: #ffffff; color: #0f172a; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 20px 40px rgb(0 0 0 / .3); }
        .dark .erp-app-prompt__card { background: #111827; color: #f1f5f9; }
        .erp-app-prompt__title { font-size: 1.125rem; font-weight: 700; padding-right: 2rem; }
        .erp-app-prompt__muted { font-size: .8125rem; opacity: .75; line-height: 1.45; }
        .erp-app-prompt__text { margin-top: .875rem; font-size: .9375rem; line-height: 1.5; white-space: pre-line; }
        .erp-app-prompt__close { position: absolute; top: .75rem; right: .75rem; width: 2rem; height: 2rem; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; line-height: 1; color: inherit; opacity: .6; background: transparent; border: 0; cursor: pointer; }
        .erp-app-prompt__never { margin-top: .875rem; width: 100%; text-align: center; font-size: .8125rem; text-decoration: underline; color: inherit; opacity: .7; background: transparent; border: 0; cursor: pointer; }
    </style>
    <div
        x-data="{
            open: false,
            key: 'erp-mobile-app-prompt',
            release: @js($mobileApp->release()),
            init() {
                const mobile = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (/Android/i.test(navigator.userAgent) && (navigator.userAgentData?.mobile ?? /Mobile/i.test(navigator.userAgent)));
                let dismissed = null;
                try { dismissed = JSON.parse(localStorage.getItem(this.key) || 'null'); } catch (e) {}
                const recent = dismissed && dismissed.release === this.release && Date.now() - dismissed.at < 14 * 24 * 3600 * 1000;
                this.open = mobile && ! recent;
            },
            later() {
                try { localStorage.setItem(this.key, JSON.stringify({ release: this.release, at: Date.now() })); } catch (e) {}
                this.open = false;
            },
            never() {
                this.open = false;
                fetch(@js(route('mobile-app.dismiss')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', 'Accept': 'application/json' },
                });
            },
        }"
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="later()"
        x-on:click.self="later()"
        class="erp-app-prompt"
        data-mobile-app-prompt
    >
        <div role="dialog" aria-modal="true" aria-labelledby="mobile-app-title" class="erp-app-prompt__card">
            <button type="button" class="erp-app-prompt__close" x-on:click="later()" aria-label="{{ __('erp.mobile_app.close') }}">✕</button>
            <div style="display: flex; gap: .875rem; align-items: center;">
                <x-filament::icon icon="heroicon-o-device-phone-mobile" style="width: 2.5rem; height: 2.5rem; color: var(--primary-600); flex-shrink: 0;" />
                <div>
                    <h2 id="mobile-app-title" class="erp-app-prompt__title">{{ __('erp.mobile_app.prompt_title', ['app' => $mobileApp->name()]) }}</h2>
                    @if ($version = $mobileApp->version())
                        <p class="erp-app-prompt__muted">{{ __('erp.mobile_app.version', ['version' => $version]) }}</p>
                    @endif
                </div>
            </div>
            <p class="erp-app-prompt__text" data-mobile-app-message>{{ $mobileApp->message() }}</p>
            @unless ($mobileApp->isStoreLink($appLink))
                <p class="erp-app-prompt__muted" style="margin-top: .5rem;">{{ __('erp.mobile_app.install_help') }}</p>
            @endunless
            <div style="display: flex; gap: .5rem; margin-top: 1.125rem;">
                <x-filament::button tag="a" :href="$appLink" :target="$mobileApp->isStoreLink($appLink) ? '_blank' : null" icon="heroicon-o-arrow-down-tray" style="flex: 1;" x-on:click="later()">
                    {{ $mobileApp->linkLabel($appLink) }}
                </x-filament::button>
                <x-filament::button color="gray" x-on:click="later()">
                    {{ __('erp.mobile_app.later') }}
                </x-filament::button>
            </div>
            <button type="button" class="erp-app-prompt__never" x-on:click="never()">{{ __('erp.mobile_app.never') }}</button>
        </div>
    </div>
@endif
