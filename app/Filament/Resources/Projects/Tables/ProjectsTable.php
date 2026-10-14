<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Support\ProjectProgress;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'workSite', 'leaders', 'poNumbers'])->withCount('steps'))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('erp.projects.search'))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (Project $record): ?string => $record->subtitle())
                    ->searchable(['reference', 'work_order_number', 'short_description', 'client_name', 'client_company'])
                    ->sortable()
                    ->copyable(),
                TextColumn::make('category.code')
                    ->label(__('erp.resources.project_category.singular'))
                    ->badge()
                    ->color('gray')
                    ->tooltip(fn (Project $record): string => $record->category->label())
                    ->sortable(),
                TextColumn::make('place')
                    ->label(__('erp.projects.place'))
                    ->state(fn (Project $record): ?string => $record->workSite ? trim(implode(' ', array_filter([$record->workSite->postal_code, $record->workSite->city]))) : $record->city)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
                // Progress bar of the 610–650 projects; only on the tabs where such projects can be.
                ViewColumn::make('progress')
                    ->label(__('erp.project_progress.title'))
                    ->view('filament.projects.progress-column')
                    ->state(fn (Project $record): ?array => ProjectProgress::appliesTo($record) ? [
                        'done' => (int) $record->steps_count,
                        'total' => count(ProjectProgress::STEPS),
                    ] : null)
                    ->visible(fn ($livewire): bool => ! property_exists($livewire, 'activeTab') || in_array($livewire->activeTab, [null, '', 'all', 'archived', ...ProjectProgress::CATEGORIES], true))
                    ->toggleable(),
                TextColumn::make('poNumbers.number')
                    ->label(__('erp.projects.po_numbers'))
                    ->fontFamily(FontFamily::Mono)
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('leaders.name')
                    ->label(__('erp.projects.leaders'))
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('erp.projects.updated'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_category_id')
                    ->label(__('erp.resources.project_category.singular'))
                    ->options(fn (): array => ProjectCategory::query()->orderBy('code')->get()->mapWithKeys(fn (ProjectCategory $category): array => [$category->id => $category->label()])->all())
                    ->multiple(),
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(ProjectStatus::class)
                    ->multiple(),
                SelectFilter::make('leader')
                    ->label(__('erp.projects.leaders'))
                    ->relationship('leaders', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('team')
                    ->label(__('erp.resources.team.singular'))
                    ->relationship('teams', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preload()
                    ->visible(fn (): bool => modules()->teams()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
