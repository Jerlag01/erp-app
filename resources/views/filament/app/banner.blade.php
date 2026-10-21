{{-- The administrators' message to employees, in the top bar left of the idea box. Inline styles: Filament ships precompiled CSS. --}}
@php($banner = app(\App\Support\EmployeeBanner::class))
@if (filament()->getCurrentPanel()?->getId() === 'app' && ($message = $banner->message()))
    <div role="status" data-employee-banner title="{{ $message }}"
         style="display: inline-flex; align-items: center; gap: .5rem; min-width: 0; max-width: min(42rem, 45vw); padding: .375rem .75rem; border-radius: .5rem; border: 1px solid rgb({{ $banner->rgb() }} / .45); background: rgb({{ $banner->rgb() }} / .12); font-size: .875rem; font-weight: 600;">
        <x-filament::icon icon="heroicon-o-megaphone" style="flex: none; width: 1.125rem; height: 1.125rem; color: rgb({{ $banner->rgb() }});" />
        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $message }}</span>
    </div>
@endif
