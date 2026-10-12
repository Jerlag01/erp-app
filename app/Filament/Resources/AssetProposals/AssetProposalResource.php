<?php

namespace App\Filament\Resources\AssetProposals;

use App\Enums\AssetProposalStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\AssetChecks\AssetCheckResource;
use App\Filament\Resources\AssetProposals\Pages\ManageAssetProposals;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetProposal;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Equipment that teams added during the yearly control. An administrator (or "assets.control")
 * confirms it, which creates the equipment, or rejects it.
 */
class AssetProposalResource extends Resource
{
    protected static ?string $model = AssetProposal::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'asset-proposals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('erp.asset_proposals.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.asset_proposals.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = AssetProposal::query()->where('status', AssetProposalStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['team', 'check', 'category', 'proposer', 'handler', 'asset']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.asset_proposals.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPlusCircle)
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (AssetProposal $record): ?string => collect([$record->category?->name, $record->brand, $record->model])->filter()->implode(' · ') ?: null),
                TextColumn::make('serial_number')->label(__('erp.fields.serial_number'))->placeholder('—')->searchable(),
                TextColumn::make('team.name')
                    ->label(ucfirst(__('erp.resources.team.singular')))
                    ->description(fn (AssetProposal $record): ?string => $record->check ? ucfirst(__('erp.asset_checks.singular')).' '.$record->check->year : null)
                    ->url(fn (AssetProposal $record): ?string => $record->check ? AssetCheckResource::getUrl('view', ['record' => $record->check]) : null),
                TextColumn::make('result')->label(__('erp.asset_checks.result'))->badge()->placeholder('—'),
                TextColumn::make('remark')->label(__('erp.asset_checks.remark'))->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('erp.asset_checks.proposed_by'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (AssetProposal $record): ?string => $record->proposer?->name),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (AssetProposal $record): ?string => $record->isPending() ? null : trim(($record->handler?->name ?? '').' '.($record->handled_at?->format('d/m/Y') ?? '')))
                    ->url(fn (AssetProposal $record): ?string => $record->asset ? AssetResource::getUrl('view', ['record' => $record->asset]) : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(AssetProposalStatus::class)
                    ->default(AssetProposalStatus::Pending->value),
                SelectFilter::make('team_id')
                    ->label(ucfirst(__('erp.resources.team.singular')))
                    ->relationship('team', 'name'),
            ])
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
                DeleteAction::make(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('erp.asset_proposals.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (AssetProposal $record): bool => $record->isPending() && auth()->user()?->can('update', $record))
            ->modalHeading(__('erp.asset_proposals.approve'))
            ->modalDescription(__('erp.asset_proposals.approve_help'))
            ->fillForm(fn (AssetProposal $record): array => [
                'asset_tag' => AssetForm::nextAssetTag(),
                'name' => $record->name,
                'asset_category_id' => $record->asset_category_id,
                'brand' => $record->brand,
                'model' => $record->model,
                'serial_number' => $record->serial_number,
            ])
            ->schema([
                TextInput::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->required()
                    ->maxLength(40)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (Asset::query()->where('asset_tag', strtoupper(trim((string) $value)))->exists()) {
                            $fail(__('validation.unique', ['attribute' => __('erp.fields.asset_tag')]));
                        }
                    }),
                TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(150),
                Select::make('asset_category_id')
                    ->label(__('erp.fields.category'))
                    ->options(fn (): array => AssetCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->rule(Rule::exists('asset_categories', 'id'))
                    ->searchable(),
                TextInput::make('brand')->label(__('erp.fields.brand'))->maxLength(60),
                TextInput::make('model')->label(__('erp.fields.model'))->maxLength(100),
                TextInput::make('serial_number')->label(__('erp.fields.serial_number'))->maxLength(100),
            ])
            ->action(function (array $data, AssetProposal $record): void {
                /** @var User $user */
                $user = auth()->user();
                $asset = $record->approve($data, $user);

                if ($record->proposer && $record->proposer->id !== $user->id) {
                    Notification::make()
                        ->title(__('erp.asset_proposals.approved_notification', ['name' => $asset->name, 'tag' => $asset->asset_tag], $record->proposer->preferredLocale()))
                        ->success()
                        ->sendToDatabase($record->proposer);
                }

                Notification::make()->title(__('erp.asset_proposals.approved', ['tag' => $asset->asset_tag]))->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('erp.asset_proposals.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (AssetProposal $record): bool => $record->isPending() && auth()->user()?->can('update', $record))
            ->schema([
                TextInput::make('reason')->label(__('erp.asset_proposals.reason'))->maxLength(255),
            ])
            ->action(function (array $data, AssetProposal $record): void {
                /** @var User $user */
                $user = auth()->user();
                $record->reject($data['reason'] ?? null, $user);

                if ($record->proposer && $record->proposer->id !== $user->id) {
                    Notification::make()
                        ->title(__('erp.asset_proposals.rejected_notification', ['name' => $record->name], $record->proposer->preferredLocale()))
                        ->body($record->rejection_reason)
                        ->danger()
                        ->sendToDatabase($record->proposer);
                }

                Notification::make()->title(__('erp.asset_proposals.rejected'))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssetProposals::route('/'),
        ];
    }
}
