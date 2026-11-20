<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\ProjectGpwBypass;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The log of "No GPW = no planning": every planning of the project without an approved GPW request,
 * by whom, why and what was planned. Read only; shown once there is one.
 */
class GpwBypassesRelationManager extends RelationManager
{
    protected static string $relationship = 'gpwBypasses';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedShieldExclamation;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.gpw.bypass_log');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        return (string) $ownerRecord->gpwBypasses()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return Gate::allows('view', $ownerRecord) && $ownerRecord->gpwBypasses()->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label(__('erp.fields.date'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label(__('erp.gpw.bypass_by'))->placeholder('—'),
                TextColumn::make('planned')->label(__('erp.gpw.bypass_planned'))->placeholder('—')->wrap(),
                TextColumn::make('reason')
                    ->label(__('erp.gpw.bypass_reason'))
                    ->state(fn (ProjectGpwBypass $record): string => $record->reason ?? __('erp.gpw.bypass_without_reason'))
                    ->color(fn (ProjectGpwBypass $record): ?string => $record->reason === null ? 'warning' : null)
                    ->wrap(),
            ])
            ->defaultSort('id', 'desc');
    }
}
