<?php

namespace App\Filament\Resources\KbArticles\Pages;

use App\Filament\App\Pages\KnowledgeBase;
use App\Filament\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditKbArticle extends EditRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'updated_by' => auth()->id()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('read')
                ->label(__('erp.kb.read'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (KbArticle $record): string => KnowledgeBase::articleUrl($record)),
            DeleteAction::make(),
        ];
    }
}
