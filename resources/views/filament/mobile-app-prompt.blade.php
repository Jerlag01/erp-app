{{--
    Suggests the Android app to someone who signed in on an Android phone. "Later" hides it on this phone
    for 14 days; a new version (or a new upload) shows it again. Inline styles: Filament ships
    precompiled CSS.
--}}
@php($mobileApp = app(\App\Support\MobileApp::class))
@if (auth()->check() && $mobileApp->shouldPrompt(request()->userAgent()))
    @php($release = settings()->get('mobile_app.version') ?: settings()->get('mobile_app.uploaded_at'))
    <div
        x-data="{
            open: false,
            key: 'erp-mobile-app-prompt',
            release: @js((string) $release),
            init() {
                const mobile = /Android/i.test(navigator.userAgent) && (navigator.userAgentData?.mobile ?? /Mobile/i.test(navigator.userAgent));
                let dismissed = null;
                try { dismissed = JSON.parse(localStorage.getItem(this.key) || 'null'); } catch (e) {}
                const recent = dismissed && dismissed.release === this.release && Date.now() - dismissed.at < 14 * 24 * 3600 * 1000;
                this.open = mobile && ! recent;
            },
            later() {
                try { localStorage.setItem(this.key, JSON.stringify({ release: this.release, at: Date.now() })); } catch (e) {}
                this.open = false;
            },
        }"
        x-show="open"
        x-cloak
        data-mobile-app-prompt
        style="position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: center; background: rgb(15 23 42 / .55); padding: 1rem;"
        x-on:keydown.escape.window="later()"
    >
        <div role="dialog" aria-modal="true" aria-labelledby="mobile-app-title" style="width: 100%; max-width: 26rem; background: var(--erp-surface, #fff); border-radius: 1rem; padding: 1.25rem; box-shadow: 0 20px 40px rgb(0 0 0 / .25);" class="dark:bg-gray-900">
            <div style="display: flex; gap: .875rem; align-items: center;">
                <x-filament::icon icon="heroicon-o-device-phone-mobile" style="width: 2.5rem; height: 2.5rem; color: var(--primary-600); flex-shrink: 0;" />
                <div>
                    <h2 id="mobile-app-title" style="font-size: 1.125rem; font-weight: 700;">{{ __('erp.mobile_app.prompt_title', ['app' => settings()->siteName()]) }}</h2>
                    @if ($version = $mobileApp->version())
                        <p style="font-size: .8125rem; opacity: .7;">{{ __('erp.mobile_app.version', ['version' => $version]) }}</p>
                    @endif
                </div>
            </div>
            <p style="margin-top: .875rem; font-size: .9375rem; line-height: 1.5;">{{ __('erp.mobile_app.prompt_body') }}</p>
            <p style="margin-top: .5rem; font-size: .8125rem; opacity: .75; line-height: 1.45;">{{ __('erp.mobile_app.install_help') }}</p>
            <div style="display: flex; gap: .5rem; margin-top: 1.125rem;">
                <x-filament::button tag="a" :href="route('mobile-app.download')" icon="heroicon-o-arrow-down-tray" style="flex: 1;" x-on:click="later()">
                    {{ __('erp.mobile_app.download') }}
                </x-filament::button>
                <x-filament::button color="gray" x-on:click="later()">
                    {{ __('erp.mobile_app.later') }}
                </x-filament::button>
            </div>
        </div>
    </div>
@endif
