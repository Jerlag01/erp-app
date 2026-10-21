{{-- The announcement for employees, in the top bar left of the idea box (module "announcements"). Inline styles: Filament ships precompiled CSS. --}}
@php($banner = app(\App\Support\EmployeeBanner::class))
@if (filament()->getCurrentPanel()?->getId() === 'app' && ($message = $banner->message()))
    <div role="status" data-employee-banner title="{{ $message }}"
         style="display: inline-flex; align-items: center; gap: .5rem; min-width: 0; max-width: min(42rem, 45vw); padding: .375rem .75rem; border-radius: .5rem; border: 1px solid rgb({{ $banner->rgb() }} / .45); background: rgb({{ $banner->rgb() }} / .12); font-size: .875rem; font-weight: 600;">
        <x-filament::icon icon="heroicon-o-megaphone" style="flex: none; width: 1.125rem; height: 1.125rem; color: rgb({{ $banner->rgb() }});" />
        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $message }}</span>
        @if ($banner->canEdit(auth()->user()))
            <a href="{{ \App\Filament\App\Pages\Announcement::getUrl(panel: 'app') }}" title="{{ __('erp.announcements.edit') }}" aria-label="{{ __('erp.announcements.edit') }}" style="flex: none; display: inline-flex; opacity: .7;">
                <x-filament::icon icon="heroicon-o-pencil-square" style="width: 1rem; height: 1rem;" />
            </a>
        @endif
    </div>
@endif
