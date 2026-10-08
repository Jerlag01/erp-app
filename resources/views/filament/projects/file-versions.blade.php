{{-- Every uploaded version of a project file, newest first. --}}
<ul style="display: flex; flex-direction: column; gap: .5rem; margin: 0; padding: 0; list-style: none;" data-file-versions>
    @foreach ($versions as $version)
        <li style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .5rem .75rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .5rem;">
            <div style="min-width: 0;">
                <div style="font-weight: 600;">
                    v{{ $version->version }}
                    @if ($version->version === $file->version)
                        <span style="font-size: .75rem; font-weight: 500; color: rgb(22 163 74);">· {{ __('erp.projects.files.current') }}</span>
                    @endif
                </div>
                <div style="font-size: .8125rem; opacity: .75; overflow-wrap: anywhere;">
                    {{ $version->original_name }} · {{ $version->humanSize() }} · {{ $version->created_at?->format('d/m/Y H:i') }}@if ($version->user) · {{ $version->user->name }}@endif
                </div>
            </div>
            <a href="{{ route('projects.file', ['project' => $file->project_id, 'file' => $file, 'version' => $version->version]) }}" target="_blank" rel="noopener" style="flex-shrink: 0; text-decoration: underline; font-size: .875rem;">{{ __('erp.projects.files.download') }}</a>
        </li>
    @endforeach
</ul>
