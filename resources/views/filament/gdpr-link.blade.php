{{-- Link to the public GDPR statement, under the sign-in form of every panel. --}}
<p style="margin-top: 1rem; text-align: center; font-size: .8125rem;">
    <a href="{{ route('gdpr', ['lang' => app()->getLocale()]) }}" target="_blank" rel="noopener" style="text-decoration: underline; opacity: .8;" data-gdpr-link>{{ __('gdpr.menu') }}</a>
</p>
