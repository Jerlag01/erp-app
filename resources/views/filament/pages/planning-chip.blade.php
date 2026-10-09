{{-- One planning item in the calendar. A project opens the project; other items open their details. --}}
@php($item = $entry['item'])
@php($tasks = $item->taskList())
<div @class(['erp-plan-chip', 'has-conflict' => $entry['conflict']]) style="--kind: {{ $entry['color'] }};" data-item="{{ $item->id }}"
    title="{{ $item->user->name }} · {{ $item->timeRange() }} · {{ $item->kind->getLabel() }}">
    <div class="erp-plan-chip-top">
        <span class="erp-plan-time">{{ $item->timeRange() }}</span>
        <span class="erp-plan-icons">
            @if ($entry['conflict'])
                <span class="erp-plan-warn" title="{{ __('erp.planning.conflict', ['reason' => $entry['conflict']]) }}"><x-filament::icon icon="heroicon-m-exclamation-triangle" /></span>
            @endif
            @if ($tasks)
                <span class="erp-plan-badge" title="{{ __('erp.planning.tasks') }}">{{ collect($tasks)->where('done', true)->count() }}/{{ count($tasks) }}</span>
            @endif
            @if ($entry['url'])
                <button type="button" class="erp-plan-icon" wire:click="mountAction('viewItem', @js(['item' => $item->id]))" title="{{ __('erp.actions.view') }}" aria-label="{{ __('erp.actions.view') }}"><x-filament::icon icon="heroicon-m-information-circle" /></button>
            @endif
            @if ($entry['canManage'])
                <button type="button" class="erp-plan-icon" wire:click="mountAction('editItem', @js(['item' => $item->id]))" title="{{ __('erp.actions.edit') }}" aria-label="{{ __('erp.actions.edit') }}"><x-filament::icon icon="heroicon-m-pencil-square" /></button>
            @endif
        </span>
    </div>
    @if ($entry['url'])
        <a href="{{ $entry['url'] }}" class="erp-plan-topic">{{ $item->topic() }}</a>
    @else
        <button type="button" class="erp-plan-topic" wire:click="mountAction('viewItem', @js(['item' => $item->id]))">{{ $item->topic() }}</button>
    @endif
    @if ($showPerson)
        <span class="erp-plan-sub">{{ $item->user->name }}</span>
    @elseif ($item->subtitle())
        <span class="erp-plan-sub">{{ $item->subtitle() }}</span>
    @endif
</div>
