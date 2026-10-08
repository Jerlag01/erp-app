<?php

namespace App\Filament\Resources\WorkSites\RelationManagers;

use App\Models\WorkSite;
use App\Models\WorkSiteRemark;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Free remarks on a COW code, added and removed whenever needed.
 */
class RemarksRelationManager extends RelationManager
{
    protected static string $relationship = 'remarks';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.work_sites.remarks');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->remarks()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label(__('erp.work_sites.remark'))
                ->required()
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var WorkSite $site */
        $site = $this->getOwnerRecord();
        $canRemark = fn (): bool => Gate::allows('remark', $site);

        return $table
            ->recordTitleAttribute('body')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->emptyStateHeading(__('erp.work_sites.no_remarks'))
            ->columns([
                TextColumn::make('body')
                    ->label(__('erp.work_sites.remark'))
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('erp.it.by'))
                    ->description(fn (WorkSiteRemark $record): string => $record->created_at->format('d/m/Y H:i'))
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('erp.work_sites.add_remark'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canRemark)
                    ->authorize($canRemark)
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->visible($canRemark)->authorize($canRemark),
                DeleteAction::make()->visible($canRemark)->authorize($canRemark),
            ]);
    }
}
