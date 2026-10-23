<?php

namespace App\Filament\Admin\Resources\MailTemplates;

use App\Enums\MailTemplateKey;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\MailTemplates\Pages\ManageMailTemplates;
use App\Models\MailTemplate;
use App\Support\MailTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Every e-mail the system can send, with its subject and text per language. Placeholders such
 * as {client_name} are filled in when the mail goes out.
 */
class MailTemplateResource extends Resource
{
    protected static ?string $model = MailTemplate::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'mail-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.resources.mail_template.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.mail_template.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(fn (?MailTemplate $record): string => __('erp.mail_templates.placeholders', [
                    'list' => implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $record?->key->placeholders() ?? [])),
                ])),
                TextInput::make('subject')->label(__('erp.mail_templates.subject'))->required()->maxLength(255),
                MarkdownEditor::make('body')
                    ->label(__('erp.mail_templates.body'))
                    ->helperText(__('erp.mail_templates.body_help'))
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                    ->required()
                    ->minHeight('18rem'),
                Toggle::make('is_active')->label(__('erp.mail_templates.is_active'))->helperText(__('erp.mail_templates.is_active_help')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('editor')->orderBy('key')->orderByRaw("FIELD(locale, 'nl', 'fr', 'en')"))
            ->defaultGroup(Group::make('key')->getTitleFromRecordUsing(fn (MailTemplate $record): string => $record->key->getLabel())->collapsible())
            ->paginated(false)
            ->columns([
                TextColumn::make('locale')->label(__('erp.fields.language'))->badge()->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('subject')->label(__('erp.mail_templates.subject'))->wrap(),
                IconColumn::make('is_active')->label(__('erp.mail_templates.custom'))->boolean(),
                TextColumn::make('updated_at')->label(__('erp.kb.updated'))->since()->description(fn (MailTemplate $record): ?string => $record->editor?->name),
            ])
            ->filters([
                SelectFilter::make('key')->label(__('erp.mail_templates.mail'))->options(MailTemplateKey::class),
                SelectFilter::make('locale')->label(__('erp.fields.language'))->options(config('app.locales')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'updated_by' => auth()->id()]),
                Action::make('reset')
                    ->label(__('erp.mail_templates.reset'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.mail_templates.reset_help'))
                    ->action(function (MailTemplate $record): void {
                        $record->update([...MailTemplates::default($record->key, $record->locale), 'updated_by' => auth()->id()]);
                        Notification::make()->title(__('erp.mail_templates.reset_done'))->success()->send();
                    }),
                Action::make('preview')
                    ->label(__('erp.mail_templates.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (MailTemplate $record) => view('mail.preview', MailTemplates::render(
                        $record->key,
                        $record->locale,
                        self::sampleValues() + array_fill_keys($record->key->placeholders(), '…'),
                    ))),
            ]);
    }

    /**
     * Example values for the preview.
     *
     * @return array<string, string|HtmlString>
     */
    protected static function sampleValues(): array
    {
        return [
            'name' => 'Eva Peeters', 'email' => 'eva@example.com', 'login_url' => url('/login'), 'password_link' => url('/login'), 'link_days' => '7',
            'client_name' => 'Voorbeeld NV', 'number' => now()->year.'-0042', 'amount' => '€ 1.210,00', 'issue_date' => today()->format('d/m/Y'),
            'due_date' => today()->addDays(30)->format('d/m/Y'), 'days_overdue' => '14', 'structured_reference' => '+++126/0000/04277+++',
            'iban' => 'BE68 5390 0754 7034', 'subject' => 'Elektriciteitswerken',
            'changed_by' => 'Jan Janssens', 'planning_url' => url('/planning'),
            'items' => new HtmlString('- **'.today()->addDay()->format('d/m/Y').' 07:30–16:00** · 604-001-02GAM'),
            'company' => 'Proximus', 'portal_name' => __('erp.client_portal.brand'), 'portal_url' => url('/client/login'),
            'reset_link' => url('/client/login'), 'minutes' => '60',
            'contact_name' => 'Marc Janssens', 'project' => '610-012-02GAM', 'cow_code' => '02GAM', 'address' => 'Stationsstraat 1, 9000 Gent',
            'description' => 'Vervangen gelijkrichter', 'period' => today()->addWeek()->format('d/m/Y').' – '.today()->addWeek()->addDays(2)->format('d/m/Y'),
            'from' => today()->addWeek()->format('d/m/Y'), 'until' => today()->addWeek()->addDays(2)->format('d/m/Y'), 'remarks' => 'Toegang via achterpoort',
            'requested_by' => 'Jan Janssens', 'requester_email' => 'jan@example.com', 'approve_url' => url('/'), 'portal_url' => url('/client'),
            'decided_by' => 'Marc Janssens', 'note' => '—', 'project_url' => url('/'),
            'author' => 'Jan Janssens', 'type' => 'idee', 'submitted_on' => today()->format('d/m/Y'), 'message' => 'Goed idee, we nemen het mee in het volgende overleg.', 'url' => url('/'),
            'overviews' => new HtmlString("- Projecten (600, 604, 605)\n- Batterijleveringen"),
            'previous' => new HtmlString('- **'.today()->format('d/m/Y').' 07:30–12:00** · 604-001-02GAM'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMailTemplates::route('/'),
        ];
    }
}
