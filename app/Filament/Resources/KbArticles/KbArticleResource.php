<?php

namespace App\Filament\Resources\KbArticles;

use App\Enums\KbFormat;
use App\Enums\NavigationGroup;
use App\Filament\App\Pages\KnowledgeBase;
use App\Filament\Resources\KbArticles\Pages\CreateKbArticle;
use App\Filament\Resources\KbArticles\Pages\EditKbArticle;
use App\Filament\Resources\KbArticles\Pages\ListKbArticles;
use App\Filament\Support\TranslatableField;
use App\Models\KbArticle;
use App\Models\KbCategory;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Writing and managing knowledge base articles (editors only); reading happens on the
 * "Knowledge base" page, which every employee can open.
 */
class KbArticleResource extends Resource
{
    protected static ?string $model = KbArticle::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'knowledge-base-articles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::KnowledgeBase;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.resources.kb_article.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.kb_article.plural');
    }

    /**
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        return KbCategory::query()->ordered()->get()
            ->mapWithKeys(fn (KbCategory $category): array => [$category->id => $category->translate('name')])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $imageTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif'];

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('kb_category_id')
                        ->label(__('erp.kb.category'))
                        ->options(fn (): array => self::categoryOptions())
                        ->required(),
                    TextInput::make('sort_order')
                        ->label(__('erp.kb.sort_order'))
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    TranslatableField::make('title', __('erp.kb.title_field'), fn (string $path) => TextInput::make($path)->maxLength(255)),
                    ToggleButtons::make('format')
                        ->label(__('erp.kb.format'))
                        ->helperText(__('erp.kb.format_help'))
                        ->options(KbFormat::class)
                        ->default(KbFormat::RichText)
                        ->inline()
                        ->live()
                        ->required()
                        ->columnSpanFull(),
                    // Pictures go anywhere in the text: the image button (or paste / drag and drop) places
                    // one at the cursor, the corners resize it, columns put a picture beside the text.
                    self::bodyEditor(KbFormat::RichText, fn (string $path) => RichEditor::make($path)
                        ->helperText(__('erp.kb.images_help'))
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', 'link'],
                            ['h2', 'h3', 'paragraph'],
                            ['alignStart', 'alignCenter', 'alignEnd'],
                            ['bulletList', 'orderedList', 'blockquote', 'codeBlock', 'horizontalRule'],
                            ['attachFiles', 'grid', 'table', 'details'],
                            ['clearFormatting', 'undo', 'redo'],
                        ])
                        ->resizableImages()
                        ->fileAttachmentsDisk(KbArticle::IMAGE_DISK)
                        ->fileAttachmentsDirectory('kb/images')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsAcceptedFileTypes($imageTypes)
                        ->fileAttachmentsMaxSize(KbArticle::IMAGE_MAX_KILOBYTES)
                        ->extraInputAttributes(['style' => 'min-height: 20rem'])),
                    self::bodyEditor(KbFormat::Markdown, fn (string $path) => MarkdownEditor::make($path)
                        ->helperText(__('erp.kb.images_help_markdown'))
                        ->fileAttachmentsDisk(KbArticle::IMAGE_DISK)
                        ->fileAttachmentsDirectory('kb/images')
                        ->fileAttachmentsAcceptedFileTypes($imageTypes)
                        ->fileAttachmentsMaxSize(KbArticle::IMAGE_MAX_KILOBYTES)
                        ->minHeight('20rem')),
                    FileUpload::make('attachments')
                        ->label(__('erp.kb.downloads'))
                        ->helperText(__('erp.kb.downloads_help'))
                        ->disk(KbArticle::FILE_DISK)
                        ->directory('kb/files')
                        ->visibility('private')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->maxFiles(20)
                        ->maxSize(51200)
                        ->rules(['extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg,gif,webp,dwg,dxf'])
                        ->storeFileNamesIn('attachment_names')
                        ->columnSpanFull(),
                    Toggle::make('is_published')
                        ->label(__('erp.kb.is_published'))
                        ->helperText(__('erp.kb.is_published_help'))
                        ->default(true),
                ]),
        ]);
    }

    /**
     * One editor per language; only the editor for the chosen format is shown and saved.
     *
     * @param  Closure(string $statePath): Field  $makeField
     */
    private static function bodyEditor(KbFormat $format, Closure $makeField): Tabs
    {
        $keyedField = fn (string $path): Field => $makeField($path)->key(str_replace('.', '-', $path)."-{$format->value}");

        return TranslatableField::make('body', __('erp.kb.body'), $keyedField)
            ->key("body-{$format->value}")
            ->visible(fn (Get $get): bool => KbFormat::tryFrom($get('format') instanceof KbFormat ? $get('format')->value : (string) $get('format')) === $format);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'editor']))
            ->columns([
                TextColumn::make('title')
                    ->label(__('erp.kb.title_field'))
                    ->state(fn (KbArticle $record): string => $record->translate('title'))
                    ->searchable(query: fn ($query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label(__('erp.kb.category'))
                    ->state(fn (KbArticle $record): string => $record->category->translate('name'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('format')
                    ->label(__('erp.kb.format'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_published')
                    ->label(__('erp.kb.is_published')),
                TextColumn::make('updated_at')
                    ->label(__('erp.kb.updated'))
                    ->since()
                    ->description(fn (KbArticle $record): ?string => $record->editor?->name)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kb_category_id')
                    ->label(__('erp.kb.category'))
                    ->options(fn (): array => self::categoryOptions()),
            ])
            ->recordActions([
                Action::make('read')
                    ->label(__('erp.kb.read'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (KbArticle $record): string => KnowledgeBase::articleUrl($record)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKbArticles::route('/'),
            'create' => CreateKbArticle::route('/create'),
            'edit' => EditKbArticle::route('/{record}/edit'),
        ];
    }
}
