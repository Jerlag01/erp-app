<?php

namespace App\Filament\Resources\ToolboxMeetings;

use App\Enums\NavigationGroup;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Policies\ToolboxMeetingPolicy;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Toolbox meetings: prepared by people with "toolbox.create", read and signed by every employee.
 * Employees see the published meetings and whether they signed them; drafts only show to who
 * prepares them.
 */
class ToolboxMeetingResource extends Resource
{
    protected static ?string $model = ToolboxMeeting::class;

    protected static ?string $slug = 'toolbox-meetings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Safety;

    protected static ?int $navigationSort = 5;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('erp.toolbox.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.toolbox.plural');
    }

    /**
     * How many published meetings the signed-in user still has to sign.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! ToolboxMeetingPolicy::enabled()) {
            return null;
        }

        $open = ToolboxMeeting::query()->published()
            ->whereDoesntHave('participants', fn (Builder $query) => $query->where('user_id', $user->id)->whereNotNull('signed_at'))
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        return $user instanceof User && (ToolboxMeetingPolicy::manages($user) || ToolboxMeetingPolicy::seesSignatures($user))
            ? $query
            : $query->published();
    }

    public static function form(Schema $schema): Schema
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('erp.toolbox.sections.meeting'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('erp.toolbox.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DatePicker::make('meeting_month')
                            ->label(__('erp.toolbox.month'))
                            ->helperText(__('erp.toolbox.month_help'))
                            ->native(false)
                            ->displayFormat('F Y')
                            ->default(today()->startOfMonth())
                            ->required(),
                        TextInput::make('location')
                            ->label(__('erp.toolbox.location'))
                            ->helperText(__('erp.toolbox.location_help'))
                            ->default(fn (): string => ToolboxMeeting::companyName())
                            ->maxLength(255),
                        TagsInput::make('instructors')
                            ->label(__('erp.toolbox.instructors'))
                            ->helperText(__('erp.toolbox.instructors_help'))
                            ->suggestions(fn (): array => User::query()->active()->orderBy('name')->pluck('name')->all())
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('subject_nl')
                            ->label(__('erp.toolbox.subject_nl'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject_fr')
                            ->label(__('erp.toolbox.subject_fr'))
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make(__('erp.toolbox.sections.text'))
                    ->description(__('erp.toolbox.text_help'))
                    ->schema([
                        // Pictures go anywhere in the text: the image button (or paste / drag and drop) places
                        // one at the cursor, the corners resize it, columns put a picture beside the text.
                        RichEditor::make('body')
                            ->hiddenLabel()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', 'link'],
                                ['h2', 'h3', 'paragraph'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                                ['attachFiles', 'grid', 'table', 'details'],
                                ['clearFormatting', 'undo', 'redo'],
                            ])
                            ->resizableImages()
                            ->fileAttachmentsDisk(ToolboxMeeting::IMAGE_DISK)
                            ->fileAttachmentsDirectory('toolbox/images')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsAcceptedFileTypes($imageTypes)
                            ->fileAttachmentsMaxSize(10240)
                            ->extraInputAttributes(['style' => 'min-height: 24rem']),
                    ]),
                Section::make(__('erp.toolbox.sections.attachments'))
                    ->description(__('erp.toolbox.attachments_help'))
                    ->schema([
                        FileUpload::make('attachments')
                            ->hiddenLabel()
                            ->disk(ToolboxMeeting::FILE_DISK)
                            ->directory('toolbox/files')
                            ->visibility('private')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->maxFiles(20)
                            ->acceptedFileTypes(ToolboxMeeting::ATTACHMENT_TYPES)
                            ->maxSize(ToolboxMeeting::ATTACHMENT_MAX_KILOBYTES)
                            ->storeFileNamesIn('attachment_names')
                            ->downloadable()
                            ->openable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $staff = $user instanceof User && (ToolboxMeetingPolicy::manages($user) || ToolboxMeetingPolicy::seesSignatures($user));

        return $table
            ->defaultSort('meeting_month', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['participants as signed_count' => fn (Builder $query) => $query->whereNotNull('signed_at')])
                ->withExists(['participants as signed_by_me' => fn (Builder $query) => $query->where('user_id', auth()->id())->whereNotNull('signed_at')]))
            ->recordUrl(fn (ToolboxMeeting $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('erp.toolbox.none'))
            ->emptyStateIcon(Heroicon::OutlinedPresentationChartLine)
            ->columns([
                TextColumn::make('meeting_month')
                    ->label(__('erp.toolbox.month'))
                    ->formatStateUsing(fn (ToolboxMeeting $record): string => $record->monthLabel(app()->getLocale()))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('erp.toolbox.title'))
                    ->description(fn (ToolboxMeeting $record): string => trim($record->subject_nl.' / '.$record->subject_fr, ' /'))
                    ->searchable(['title', 'subject_nl', 'subject_fr'])
                    ->wrap(),
                TextColumn::make('instructors')
                    ->label(__('erp.toolbox.instructors'))
                    ->state(fn (ToolboxMeeting $record): string => $record->instructorNames())
                    ->toggleable(),
                IconColumn::make('signed_by_me')
                    ->label(__('erp.toolbox.my_signature'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedPencilSquare)
                    ->falseColor('warning')
                    ->tooltip(fn (ToolboxMeeting $record): string => $record->signed_by_me ? __('erp.toolbox.signed') : __('erp.toolbox.to_sign')),
                TextColumn::make('signed_count')
                    ->label(__('erp.toolbox.signatures'))
                    ->badge()
                    ->color('gray')
                    ->visible($staff),
                TextColumn::make('published_at')
                    ->label(__('erp.toolbox.status'))
                    ->state(fn (ToolboxMeeting $record): string => $record->isPublished() ? __('erp.toolbox.published') : __('erp.toolbox.draft'))
                    ->badge()
                    ->color(fn (ToolboxMeeting $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->visible($staff),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y') + 1, (int) date('Y') - 5))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $year) => $query->whereYear('meeting_month', $year))),
            ])
            ->recordActions([
                ViewAction::make()->label(__('erp.toolbox.open')),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListToolboxMeetings::route('/'),
            'create' => Pages\CreateToolboxMeeting::route('/create'),
            'view' => Pages\ViewToolboxMeeting::route('/{record}'),
            'edit' => Pages\EditToolboxMeeting::route('/{record}/edit'),
        ];
    }
}
