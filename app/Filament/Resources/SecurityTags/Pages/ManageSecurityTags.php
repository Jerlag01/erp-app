<?php

namespace App\Filament\Resources\SecurityTags\Pages;

use App\Filament\Resources\SecurityTags\SecurityTagResource;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageSecurityTags extends ManageRecords
{
    protected static string $resource = SecurityTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('erp.access.export'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'access_control'))
                ->url(fn (): string => route('access.tags-pdf', ['tab' => $this->activeTab]), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make(__('erp.access.tabs.active'))->modifyQueryUsing(fn (Builder $query) => $query->active()),
            'deactivated' => Tab::make(__('erp.access.tabs.deactivated'))->modifyQueryUsing(fn (Builder $query) => $query->active(false)),
            'all' => Tab::make(__('erp.access.tabs.all')),
        ];
    }
}
