<?php

namespace App\Filament\Resources\OnboardingSignatures\Pages;

use App\Filament\Resources\OnboardingSignatures\OnboardingSignatureResource;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOnboardingSignatures extends ListRecords
{
    protected static string $resource = OnboardingSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('erp.onboarding.export'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'onboarding'))
                ->url(fn (): string => route('onboarding.signatures-pdf', ['filters' => $this->tableFilters]), shouldOpenInNewTab: true),
        ];
    }
}
