<?php

namespace App\Filament\Admin\Resources\Suggestions\Pages;

use App\Enums\SuggestionType;
use App\Filament\Admin\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListSuggestions extends ListRecords
{
    protected static string $resource = SuggestionResource::class;

    /**
     * A separate list per type, so the entries are sorted as soon as the page opens.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Suggestion::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        $tabs = [];

        foreach (SuggestionType::cases() as $type) {
            $tabs[$type->value] = Tab::make($type->getLabel())
                ->icon(match ($type) {
                    SuggestionType::Idea => Heroicon::OutlinedLightBulb,
                    SuggestionType::Complaint => Heroicon::OutlinedChatBubbleLeftEllipsis,
                    SuggestionType::Other => Heroicon::OutlinedEllipsisHorizontalCircle,
                })
                ->badge((int) ($counts[$type->value] ?? 0))
                ->badgeColor($type->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type));
        }

        $tabs['all'] = Tab::make(__('erp.suggestions.all'))->badge((int) $counts->sum());

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return SuggestionType::Idea->value;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Everything in the current tab (with its filters) in one PDF.
            Action::make('exportTab')
                ->label(__('erp.suggestions.export_tab'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn () => $this->js('window.open('.json_encode(SuggestionResource::pdfUrl(
                    $this->getFilteredSortedTableQuery()->pluck('suggestions.id'),
                )).', "_blank")')),
        ];
    }
}
