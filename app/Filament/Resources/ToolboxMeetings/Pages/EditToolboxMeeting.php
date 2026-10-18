<?php

namespace App\Filament\Resources\ToolboxMeetings\Pages;

use App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditToolboxMeeting extends EditRecord
{
    protected static string $resource = ToolboxMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewToolboxMeeting::publishAction(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['meeting_month'] = Carbon::parse($data['meeting_month'])->startOfMonth()->toDateString();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ToolboxMeetingResource::getUrl('view', ['record' => $this->record]);
    }
}
