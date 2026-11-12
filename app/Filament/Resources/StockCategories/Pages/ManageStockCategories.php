<?php

namespace App\Filament\Resources\StockCategories\Pages;

use App\Filament\Resources\StockCategories\StockCategoryResource;
use App\Models\StockCategory;
use App\Support\Translation\Translator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageStockCategories extends ManageRecords
{
    protected static string $resource = StockCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Categories from before (or made while translation was off): fill in the missing names.
            Action::make('translateMissing')
                ->label(__('erp.stock.category_names.translate_missing'))
                ->icon(Heroicon::OutlinedLanguage)
                ->color('gray')
                ->visible(fn (): bool => app(Translator::class)->enabled() && StockCategory::query()->where(fn ($query) => $query->whereNull('name_fr')->orWhereNull('name_en'))->exists())
                ->requiresConfirmation()
                ->action(function (): void {
                    $count = 0;
                    StockCategory::query()->where(fn ($query) => $query->whereNull('name_fr')->orWhereNull('name_en'))->each(function (StockCategory $category) use (&$count): void {
                        $category->translateNames();
                        $count += (int) $category->isDirty();
                        $category->save();
                    });
                    Notification::make()->title(__('erp.stock.category_names.translated', ['count' => $count]))->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
