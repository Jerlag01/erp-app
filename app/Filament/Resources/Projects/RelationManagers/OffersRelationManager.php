<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * The offers of a project: uploaded files and the PDFs of the offers made in the finance module,
 * with a button to make a new one for this project and links to the existing ones.
 */
class OffersRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'offers';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Offers;
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);
        $project = fn () => $this->getOwnerRecord();

        return $table->headerActions([
            Action::make('makeOffer')
                ->label(__('erp.finance.offers.make'))
                ->icon(Heroicon::OutlinedDocumentCurrencyEuro)
                ->visible(fn (): bool => modules()->finance() && Gate::allows('create', Offer::class) && $this->canManage())
                ->url(fn (): string => OfferResource::getUrl('create', ['project' => $project()->id])),
            Action::make('projectOffers')
                ->label(fn (): string => __('erp.finance.offers.of_project', ['count' => Offer::query()->where('project_id', $project()->id)->count()]))
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->visible(fn (): bool => modules()->finance() && Gate::allows('viewAny', Offer::class) && Offer::query()->where('project_id', $project()->id)->exists())
                ->url(fn (): string => OfferResource::getUrl('index', ['tableSearch' => $project()->reference])),
            ...$table->getHeaderActions(),
        ]);
    }
}
