<?php

namespace App\Filament\Client\Resources\GpwRequests;

use App\Actions\Gpw\DecideGpw;
use App\Enums\GpwStatus;
use App\Filament\Client\ClientResource;
use App\Models\GpwRequest;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The GPW requests of the projects the client user sees: the period we ask to work in, and approve or
 * refuse while pending. Approved requests are then planned by us; the client sees that here too.
 */
class ClientGpwRequestResource extends ClientResource
{
    protected static ?string $model = GpwRequest::class;

    protected static ?string $slug = 'gpw-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?int $navigationSort = 2;

    public static function permission(): string
    {
        return ClientPortal::GPW;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->projects();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.gpw.title');
    }

    public static function getModelLabel(): string
    {
        return __('erp.gpw.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.gpw.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', GpwStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = ClientPortal::user();

        return GpwRequest::query()
            ->with(['project.workSite', 'requester', 'team'])
            ->where('status', '!=', GpwStatus::Cancelled)
            ->whereHas('project', fn (Builder $query) => $user ? $user->restrictProjects($query) : $query->whereRaw('1 = 0'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('erp.gpw.none'))
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.client_portal.project_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (GpwRequest $record): ?string => $record->project?->subtitle())
                    ->searchable(),
                TextColumn::make('project.cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (GpwRequest $record): ?string => $record->project?->workSite?->city)
                    ->searchable(),
                TextColumn::make('starts_on')->label(__('erp.gpw.period'))->state(fn (GpwRequest $record): string => $record->period())->weight(FontWeight::Bold)->sortable(),
                TextColumn::make('status')->label(__('erp.client_portal.status'))->badge(),
                TextColumn::make('remarks')->label(__('erp.gpw.remarks'))->wrap()->limit(120)->placeholder('—'),
                TextColumn::make('requester.name')->label(__('erp.gpw.requested_by'))->description(fn (GpwRequest $record): string => $record->created_at->format('d/m/Y')),
                TextColumn::make('decided_by_name')
                    ->label(__('erp.gpw.decision'))
                    ->description(fn (GpwRequest $record): ?string => $record->decided_at?->format('d/m/Y H:i'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.client_portal.status'))
                    ->options(collect(GpwStatus::cases())->reject(fn (GpwStatus $status): bool => $status === GpwStatus::Cancelled)->mapWithKeys(fn (GpwStatus $status): array => [$status->value => $status->getLabel()])->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('erp.gpw.approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (GpwRequest $record): bool => $record->isPending())
                    ->modalHeading(fn (GpwRequest $record): string => __('erp.gpw.approve_heading', ['project' => $record->project?->reference, 'period' => $record->period()]))
                    ->schema([Textarea::make('note')->label(__('erp.gpw.page.note'))->rows(3)->maxLength(2000)])
                    ->action(fn (GpwRequest $record, array $data, DecideGpw $decide) => $decide->approve($record, ClientPortal::user()->name, ClientPortal::user(), $data['note'] ?? null))
                    ->successNotificationTitle(__('erp.gpw.approved')),
                Action::make('reject')
                    ->label(__('erp.gpw.reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (GpwRequest $record): bool => $record->isPending())
                    ->modalHeading(fn (GpwRequest $record): string => __('erp.gpw.reject_heading', ['project' => $record->project?->reference, 'period' => $record->period()]))
                    ->schema([Textarea::make('note')->label(__('erp.gpw.reason'))->rows(3)->maxLength(2000)->required()])
                    ->action(fn (GpwRequest $record, array $data, DecideGpw $decide) => $decide->reject($record, ClientPortal::user()->name, ClientPortal::user(), $data['note']))
                    ->successNotificationTitle(__('erp.gpw.rejected')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientGpwRequests::route('/'),
        ];
    }
}
