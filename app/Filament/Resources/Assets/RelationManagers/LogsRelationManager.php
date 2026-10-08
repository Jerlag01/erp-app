<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\AssetLogs\Schemas\AssetLogForm;
use App\Filament\Resources\AssetLogs\Tables\AssetLogsTable;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The asset's damage, repair and inspection history.
 */
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.asset_log.plural');
    }

    /**
     * Employees who may log damage can do so from the asset's view page.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        return AssetLogForm::configure($schema, $asset);
    }

    public function table(Table $table): Table
    {
        return AssetLogsTable::configure($table, showAsset: false)
            ->recordTitleAttribute('title')
            ->headerActions([
                CreateAction::make()
                    ->label(__('erp.logs.new_entry'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ]);
    }
}
