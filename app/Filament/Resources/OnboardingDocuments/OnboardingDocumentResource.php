<?php

namespace App\Filament\Resources\OnboardingDocuments;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OnboardingDocuments\Pages\ManageOnboardingDocuments;
use App\Models\OnboardingDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The onboarding documents: uploaded several at once with a language; each employee sees those in
 * their own language and signs them.
 */
class OnboardingDocumentResource extends Resource
{
    protected static ?string $model = OnboardingDocument::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'onboarding-documents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 81;

    /** Up to 50 MB per document. */
    public const MAX_KILOBYTES = 51200;

    public static function getModelLabel(): string
    {
        return __('erp.onboarding.document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.onboarding.documents');
    }

    public static function localeSelect(): Select
    {
        return Select::make('locale')
            ->label(__('erp.onboarding.language'))
            ->helperText(__('erp.onboarding.language_help'))
            ->options(OnboardingDocument::LOCALES)
            ->default('nl')
            ->required();
    }

    /**
     * Editing one document: its title, language, order, and replacing its file.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->label(__('erp.onboarding.document_title'))->required()->maxLength(255)->columnSpanFull(),
                self::localeSelect(),
                TextInput::make('sort')->label(__('erp.onboarding.sort'))->integer()->minValue(0)->default(0),
                FileUpload::make('path')
                    ->label(__('erp.onboarding.file'))
                    ->disk(OnboardingDocument::DISK)
                    ->directory('onboarding/documents')
                    ->visibility('private')
                    ->maxSize(self::MAX_KILOBYTES)
                    ->storeFileNamesIn('original_name')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->label(__('erp.onboarding.document_title'))->weight(FontWeight::Bold)->description(fn (OnboardingDocument $record): string => $record->original_name)->searchable(),
                TextColumn::make('locale')->label(__('erp.onboarding.language'))->formatStateUsing(fn (string $state): string => OnboardingDocument::LOCALES[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('signed_count')
                    ->label(__('erp.onboarding.signed_at'))
                    ->counts(['signatures as signed_count' => fn ($query) => $query->whereNotNull('signed_at')])
                    ->alignEnd(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('locale')->label(__('erp.onboarding.language'))->options(OnboardingDocument::LOCALES),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('erp.onboarding.open'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->url(fn (OnboardingDocument $record): string => route('onboarding.document', $record), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOnboardingDocuments::route('/'),
        ];
    }
}
