<?php

namespace App\Filament\Admin\Resources\PushMessages;

use App\Actions\PushMessages\SendPushMessage;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\PushMessages\Pages\ManagePushMessages;
use App\Models\PushMessage;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Messages to the mobile app: written in Dutch, French and English, each employee gets the one of
 * their language as a push notification (and under "Messages" in the app). Administrators with the
 * explicit permission "push_messages.send" only. Sent messages cannot be changed.
 */
class PushMessageResource extends Resource
{
    protected static ?string $model = PushMessage::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'push-messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('erp.push_messages.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.push_messages.plural');
    }

    public static function canViewAny(): bool
    {
        return PushMessage::canSend(auth()->user());
    }

    public static function canCreate(): bool
    {
        return PushMessage::canSend(auth()->user());
    }

    public static function canView(Model $record): bool
    {
        return PushMessage::canSend(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ToggleButtons::make('audience')
                    ->label(__('erp.push_messages.audience'))
                    ->options(collect(PushMessage::AUDIENCES)->mapWithKeys(fn (string $audience): array => [$audience => __("erp.push_messages.audiences.{$audience}")])->all())
                    ->default(PushMessage::AUDIENCE_ALL)
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('team_ids')
                    ->label(__('erp.push_messages.teams'))
                    ->options(fn (): array => Team::options())
                    ->multiple()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('audience') === PushMessage::AUDIENCE_TEAMS)
                    ->live(),
                Select::make('user_ids')
                    ->label(__('erp.push_messages.users'))
                    ->options(fn (): array => User::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->required()
                    // Who got it is in the count of the list; the choice itself is not kept.
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('audience') === PushMessage::AUDIENCE_USERS)
                    ->live(),
                Text::make(fn (Get $get): string => __('erp.push_messages.recipients_preview', ['count' => app(SendPushMessage::class)->recipients([
                    'audience' => (string) $get('audience'),
                    'team_ids' => (array) $get('team_ids'),
                    'user_ids' => (array) $get('user_ids'),
                ])->count()]))
                    ->visibleOn('create'),
                Text::make(__('erp.push_messages.languages_help'))->visibleOn('create'),
                Tabs::make('languages')
                    ->tabs(collect(config('app.locales'))->map(fn (string $name, string $locale): Tab => Tab::make($name)
                        ->schema([
                            TextInput::make("title.{$locale}")
                                ->label(__('erp.push_messages.title'))
                                ->required()
                                ->maxLength(150),
                            Textarea::make("body.{$locale}")
                                ->label(__('erp.push_messages.body'))
                                ->required()
                                ->rows(4)
                                ->maxLength(1000),
                        ]))->values()->all()),
            ]);
    }

    public static function table(Table $table): Table
    {
        $locale = fn (): string => app()->getLocale();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sender')->withCount(['recipients as read_count' => fn (Builder $query) => $query->whereNotNull('push_message_user.read_at')]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('erp.push_messages.sent_at'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')
                    ->label(__('erp.push_messages.title'))
                    ->state(fn (PushMessage $record): string => $record->titleIn($locale()))
                    ->description(fn (PushMessage $record): string => str($record->bodyIn($locale()))->limit(80)->toString())
                    ->weight('medium'),
                TextColumn::make('audience')
                    ->label(__('erp.push_messages.audience'))
                    ->formatStateUsing(fn (string $state): string => __("erp.push_messages.audiences.{$state}"))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('recipients_count')
                    ->label(__('erp.push_messages.recipients'))
                    ->formatStateUsing(fn (PushMessage $record): string => __('erp.push_messages.read_of', ['read' => (int) $record->read_count, 'total' => $record->recipients_count])),
                TextColumn::make('sender.name')->label(__('erp.push_messages.sent_by'))->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePushMessages::route('/'),
        ];
    }
}
