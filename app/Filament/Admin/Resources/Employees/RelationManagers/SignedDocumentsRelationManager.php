<?php

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Models\SignedDocument;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * "Signed documents": what the employee signed (e.g. the delivery reports of IT assets), as PDF.
 * Made by the signing itself, so read-only here.
 */
class SignedDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'signedDocuments';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.signed_documents.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('erp.signed_documents.singular'))
            ->pluralModelLabel(__('erp.signed_documents.title'))
            ->defaultSort('signed_at', 'desc')
            ->emptyStateHeading(__('erp.signed_documents.none'))
            ->columns([
                TextColumn::make('title')->label(__('erp.signed_documents.document'))->searchable()->wrap(),
                TextColumn::make('kind')->label(__('erp.signed_documents.kind'))->formatStateUsing(fn (SignedDocument $record): string => $record->kindLabel())->badge()->color('gray'),
                TextColumn::make('signed_at')->label(__('erp.signed_documents.signed_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('erp.signed_documents.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (SignedDocument $record): string => route('signed-documents.download', $record)),
            ]);
    }
}
