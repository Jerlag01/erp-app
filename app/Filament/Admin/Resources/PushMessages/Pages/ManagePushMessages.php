<?php

namespace App\Filament\Admin\Resources\PushMessages\Pages;

use App\Actions\PushMessages\SendPushMessage;
use App\Filament\Admin\Resources\PushMessages\PushMessageResource;
use App\Models\PushMessage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePushMessages extends ManageRecords
{
    protected static string $resource = PushMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('erp.push_messages.new'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalSubmitActionLabel(__('erp.push_messages.send'))
                ->createAnother(false)
                ->using(fn (array $data): PushMessage => app(SendPushMessage::class)->handle($data, auth()->user()))
                ->successNotificationTitle(fn (PushMessage $record): string => __('erp.push_messages.sent', ['count' => $record->recipients_count])),
        ];
    }
}
