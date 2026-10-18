<?php

namespace App\Filament\Resources\ToolboxMeetings\Pages;

use App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

/**
 * A new meeting is a draft: employees see it once it is published.
 */
class CreateToolboxMeeting extends CreateRecord
{
    protected static string $resource = ToolboxMeetingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['meeting_month'] = Carbon::parse($data['meeting_month'])->startOfMonth()->toDateString();
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ToolboxMeetingResource::getUrl('view', ['record' => $this->record]);
    }
}
