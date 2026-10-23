<?php

namespace App\Filament\Client\Resources\Asbuilts;

use App\Enums\AsbuiltStatus;
use App\Filament\Client\ClientResource;
use App\Models\Asbuilt;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The as-builts of the projects the client user sees: when it was made, whether Normec BTV is
 * planned and whether it is finished (then the ZIP is downloaded here); per as-built an upload zone
 * for the files the client provides.
 */
class ClientAsbuiltResource extends ClientResource
{
    protected static ?string $model = Asbuilt::class;

    protected static ?string $slug = 'asbuilts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return ClientPortal::ASBUILT;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->asbuilt();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.asbuilt.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.asbuilt.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.asbuilt.title');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = ClientPortal::user();

        return Asbuilt::query()
            ->with(['project.workSite'])
            ->whereHas('project', fn (Builder $query) => $user ? $user->restrictProjects($query) : $query->whereRaw('1 = 0'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 5])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('project.reference')->label(__('erp.client_portal.project_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold),
                    TextEntry::make('status')->label(__('erp.client_portal.status'))->badge(),
                    TextEntry::make('created_at')->label(__('erp.asbuilt.created'))->date('d/m/Y'),
                    TextEntry::make('btv_date')->label(__('erp.asbuilt.btv_planned_on'))->date('d/m/Y')->placeholder(__('erp.asbuilt.not_planned')),
                    TextEntry::make('closed_at')->label(__('erp.asbuilt.finished'))->date('d/m/Y')->placeholder('—'),
                ]),
            ViewEntry::make('client_files')
                ->hiddenLabel()
                ->columnSpanFull()
                ->view('filament.client.asbuilt-files'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.asbuilt.none'))
            ->columns([
                TextColumn::make('project.reference')->label(__('erp.client_portal.project_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()
                    ->description(fn (Asbuilt $record): ?string => $record->project?->subtitle()),
                TextColumn::make('project.cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('status')->label(__('erp.client_portal.status'))->badge(),
                TextColumn::make('created_at')->label(__('erp.asbuilt.created'))->date('d/m/Y')->sortable(),
                TextColumn::make('btv_date')->label(__('erp.asbuilt.btv_planned_on'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('closed_at')->label(__('erp.asbuilt.finished'))->date('d/m/Y')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('erp.client_portal.status'))->options(AsbuiltStatus::class),
            ])
            ->recordActions([
                Action::make('zip')
                    ->label(__('erp.asbuilt.download_zip'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Asbuilt $record): bool => $record->isClosed())
                    ->url(fn (Asbuilt $record): string => route('client.asbuilt.zip', $record)),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientAsbuilts::route('/'),
            'view' => Pages\ViewClientAsbuilt::route('/{record}'),
        ];
    }
}
