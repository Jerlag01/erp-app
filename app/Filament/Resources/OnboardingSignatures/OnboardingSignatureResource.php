<?php

namespace App\Filament\Resources\OnboardingSignatures;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OnboardingSignatures\Pages\ListOnboardingSignatures;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSignature;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Who opened and signed which onboarding document, with the written signature and its time. Read-only.
 */
class OnboardingSignatureResource extends Resource
{
    protected static ?string $model = OnboardingSignature::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'onboarding-signatures';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 82;

    public static function getModelLabel(): string
    {
        return __('erp.onboarding.signature');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.onboarding.signatures');
    }

    /**
     * @param  array<string, mixed>  $filters  the table filters (user_id, document, signed)
     * @return Builder<OnboardingSignature>
     */
    public static function filteredQuery(array $filters): Builder
    {
        return OnboardingSignature::query()
            ->with(['user', 'document'])
            ->when($filters['user_id']['value'] ?? null, fn (Builder $query, $id) => $query->where('user_id', $id))
            ->when($filters['onboarding_document_id']['value'] ?? null, fn (Builder $query, $id) => $query->where('onboarding_document_id', $id))
            ->when(($filters['signed']['value'] ?? null) !== null && ($filters['signed']['value'] ?? '') !== '', fn (Builder $query) => (bool) $filters['signed']['value'] ? $query->whereNotNull('signed_at') : $query->whereNull('signed_at'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'document']))
            ->defaultSort('signed_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('erp.onboarding.employee'))
                    ->weight(FontWeight::Bold)
                    ->description(fn (OnboardingSignature $record): string => $record->user?->onboarding_completed_at ? __('erp.onboarding.complete') : __('erp.onboarding.pending'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document.title')
                    ->label(__('erp.onboarding.document_title'))
                    ->description(fn (OnboardingSignature $record): string => OnboardingDocument::LOCALES[$record->document?->locale] ?? '')
                    ->searchable(),
                TextColumn::make('opened_at')->label(__('erp.onboarding.opened_at'))->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('signed_at')->label(__('erp.onboarding.signed_at'))->dateTime('d/m/Y H:i')->sortable()->placeholder(__('erp.onboarding.not_signed')),
                TextColumn::make('signer_name')->label(__('erp.onboarding.signer_name'))->placeholder('—'),
                ViewColumn::make('signature')->label(__('erp.onboarding.signature'))->view('filament.onboarding.signature-column'),
            ])
            ->filters([
                SelectFilter::make('user_id')->label(__('erp.onboarding.employee'))->relationship('user', 'name')->searchable()->preload(),
                SelectFilter::make('onboarding_document_id')->label(__('erp.onboarding.document_title'))->relationship('document', 'title'),
                TernaryFilter::make('signed')->label(__('erp.onboarding.signed_at'))->nullable()->attribute('signed_at'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnboardingSignatures::route('/'),
        ];
    }
}
