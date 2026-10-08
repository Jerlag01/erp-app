<?php

namespace App\Filament\Resources\WorkSiteAreas;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WorkSiteAreas\Pages\ManageWorkSiteAreas;
use App\Filament\Resources\WorkSites\Schemas\WorkSiteContactFields;
use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Areas the work sites are organised by, each with a contact person.
 */
class WorkSiteAreaResource extends Resource
{
    protected static ?string $model = WorkSiteArea::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'work-site-areas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::WorkSites;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.work_site_area.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.work_site_area.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('erp.fields.name'))
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            Select::make('work_site_contact_id')
                ->label(__('erp.fields.contact_person'))
                ->helperText(__('erp.help.area_contact'))
                ->relationship('contact', 'last_name')
                ->getOptionLabelFromRecordUsing(fn (WorkSiteContact $record): string => $record->label())
                ->searchable(['first_name', 'last_name', 'company'])
                ->preload()
                ->createOptionForm(WorkSiteContactFields::make()),
            Textarea::make('description')
                ->label(__('erp.fields.description'))
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('contact')->withCount('workSites'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (WorkSiteArea $record): ?string => $record->description)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact')
                    ->label(__('erp.fields.contact_person'))
                    ->state(fn (WorkSiteArea $record): ?string => $record->contact?->label())
                    ->description(fn (WorkSiteArea $record): ?string => $record->contact?->phone ?? $record->contact?->email)
                    ->placeholder('—'),
                TextColumn::make('work_sites_count')
                    ->label(__('erp.resources.work_site.plural'))
                    ->badge()
                    ->color('gray')
                    ->url(fn (WorkSiteArea $record): string => WorkSiteResource::getUrl('index', ['filters' => ['area' => ['value' => $record->id]]]))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting an area that still has work sites.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkSiteAreas::route('/'),
        ];
    }
}
