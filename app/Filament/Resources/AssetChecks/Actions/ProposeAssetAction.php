<?php

namespace App\Filament\Resources\AssetChecks\Actions;

use App\Enums\AssetCheckResult;
use App\Enums\AssetProposalStatus;
use App\Models\AssetCategory;
use App\Models\AssetCheck;
use App\Models\AssetProposal;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * "Add equipment": the team has equipment that is not on the control list. It waits for an
 * administrator to confirm it before it becomes equipment.
 */
class ProposeAssetAction
{
    public static function make(): Action
    {
        return Action::make('propose')
            ->label(__('erp.asset_checks.propose'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('erp.asset_checks.propose'))
            ->modalDescription(__('erp.asset_checks.propose_help'))
            ->schema([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->placeholder(__('erp.help.asset_name'))
                    ->required()
                    ->maxLength(150),
                Select::make('asset_category_id')
                    ->label(__('erp.fields.category'))
                    ->options(fn (): array => AssetCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('brand')->label(__('erp.fields.brand'))->maxLength(60),
                TextInput::make('model')->label(__('erp.fields.model'))->maxLength(100),
                TextInput::make('serial_number')->label(__('erp.fields.serial_number'))->maxLength(100),
                ToggleButtons::make('result')
                    ->label(__('erp.asset_checks.result'))
                    ->options(AssetCheckResult::class)
                    ->inline(),
                TextInput::make('remark')->label(__('erp.asset_checks.remark'))->maxLength(255),
            ])
            ->action(function (array $data, RelationManager $livewire): void {
                /** @var AssetCheck $check */
                $check = $livewire->getOwnerRecord();
                abort_unless(Gate::allows('fill', $check), 403);

                AssetProposal::query()->create([
                    ...$data,
                    'asset_check_id' => $check->id,
                    'team_id' => $check->team_id,
                    'proposed_by' => auth()->id(),
                    'status' => AssetProposalStatus::Pending,
                ]);

                Notification::make()->title(__('erp.asset_checks.proposed'))->body(__('erp.asset_checks.proposed_help'))->success()->send();
            });
    }
}
