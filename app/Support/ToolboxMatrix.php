<?php

namespace App\Support;

use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The follow-up of toolbox meetings: per employee (rows) and published meeting of a year (columns)
 * whether they signed it.
 */
class ToolboxMatrix
{
    /**
     * @return array{columns: list<array{id: int, month: string, title: string}>, rows: list<array{user: User, cells: array<int, ?string>, signed: int}>, years: list<int>}
     */
    public function build(int $year, string $search = ''): array
    {
        $meetings = ToolboxMeeting::query()->published()->whereYear('meeting_month', $year)->orderBy('meeting_month')->orderBy('id')->get();
        $signatures = ToolboxMeetingParticipant::query()
            ->whereIn('toolbox_meeting_id', $meetings->modelKeys())
            ->whereNotNull('signed_at')
            ->get(['toolbox_meeting_id', 'user_id', 'signed_at'])
            ->groupBy('user_id');

        $signedUsers = User::query()->whereKey($signatures->keys())->get();
        $users = ToolboxMeeting::expectedUsers()->concat($signedUsers)->unique('id')
            ->when(trim($search) !== '', fn (Collection $users) => $users->filter(fn (User $user): bool => str_contains(mb_strtolower($user->name), mb_strtolower(trim($search)))))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $rows = $users->map(function (User $user) use ($meetings, $signatures): array {
            $mine = ($signatures->get($user->id) ?? collect())->keyBy('toolbox_meeting_id');
            $cells = $meetings->mapWithKeys(fn (ToolboxMeeting $meeting): array => [$meeting->id => $mine->get($meeting->id)?->signed_at?->format('d/m/Y')])->all();

            return ['user' => $user, 'cells' => $cells, 'signed' => count(array_filter($cells))];
        })->all();

        $years = ToolboxMeeting::query()->published()->selectRaw('YEAR(meeting_month) as year')->distinct()->pluck('year')
            ->map(fn (mixed $value): int => (int) $value)->push($year)->unique()->sortDesc()->values()->all();

        return [
            'columns' => $meetings->map(fn (ToolboxMeeting $meeting): array => [
                'id' => $meeting->id,
                'month' => $meeting->monthLabel(app()->getLocale()),
                'title' => $meeting->title,
            ])->all(),
            'rows' => $rows,
            'years' => $years,
        ];
    }
}
