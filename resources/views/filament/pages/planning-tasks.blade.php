{{-- The task list of a planning item; the employee (and planners) tick tasks off. --}}
<div>
    <strong>{{ __('erp.planning.tasks') }}</strong>
    <ul style="margin-top: .375rem; display: flex; flex-direction: column; gap: .375rem;">
        @foreach ($item->taskList() as $index => $task)
            <li>
                <label style="display: flex; align-items: flex-start; gap: .5rem; {{ $canTick ? 'cursor: pointer;' : '' }}">
                    <input type="checkbox" class="fi-checkbox-input" @checked($task['done']) @disabled(! $canTick)
                        @if ($canTick) wire:click="toggleTask({{ $item->id }}, {{ $index }})" @endif>
                    <span @style(['text-decoration: line-through; opacity: .6' => $task['done']])>{{ $task['text'] }}</span>
                </label>
            </li>
        @endforeach
    </ul>
</div>
