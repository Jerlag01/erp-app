<?php

namespace App\Filament\Resources\BatteryRooms\Schemas;

use App\Filament\Forms\SignaturePad;
use App\Filament\Resources\BatteryRooms\Pages\EditBatteryRoom;
use App\Models\BatteryRoomDossier;
use App\Support\PdfAccess;
use App\Support\PrivatePhotos;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * The battery room as the Excel "Leveringsnota 14EIN": delivery note (with its written signature),
 * extra info, checklist and the uploads (photos, ventilation calculation, BTV report).
 */
class BatteryRoomForm
{
    /**
     * Ventilation calculations come as PDF, spreadsheet, document or picture.
     */
    public const DOCUMENT_TYPES = [
        'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', ...PrivatePhotos::TYPES,
    ];

    public const MAX_KILOBYTES = 51200;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('battery_rooms.tabs.note'))->icon(Heroicon::OutlinedDocumentText)->schema(self::noteTab()),
                        Tab::make(__('battery_rooms.tabs.extra'))->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->schema([
                            Textarea::make('extra_info')
                                ->label(__('battery_rooms.fields.extra_info'))
                                ->helperText(__('battery_rooms.fields.extra_info_help'))
                                ->rows(10)
                                ->maxLength(10000),
                        ]),
                        Tab::make(__('battery_rooms.tabs.checklist'))
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->badge(fn (?BatteryRoomDossier $record): ?string => $record ? implode('/', $record->checklistProgress()) : null)
                            ->schema(self::checklistTab()),
                        Tab::make(__('battery_rooms.tabs.files'))->icon(Heroicon::OutlinedCamera)->schema(self::filesTab()),
                    ]),
            ]);
    }

    /**
     * @return array<Component>
     */
    protected static function noteTab(): array
    {
        return [
            // Locked once the delivery note is signed (except for administrators).
            Group::make([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('room_number')
                        ->label(__('battery_rooms.fields.room_number'))
                        ->helperText(__('battery_rooms.fields.room_number_help'))
                        ->maxLength(50),
                    DatePicker::make('note_date')->label(__('battery_rooms.fields.note_date'))->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('client_reference')
                        ->label(__('battery_rooms.fields.client_reference'))
                        ->helperText(__('battery_rooms.fields.client_reference_help'))
                        ->placeholder(fn (?BatteryRoomDossier $record): string => $record?->project?->poNumbers()->value('number') ?? __('battery_rooms.doc.pending', [], 'nl'))
                        ->maxLength(50),
                ]),
                Text::make(__('battery_rooms.quantities_help'))->color('gray'),
                Grid::make(['default' => 1, 'lg' => 2])->schema(array_map(
                    fn (string $section): Section => Section::make(__("battery_rooms.sections.{$section}.title"))
                        ->description(BatteryRoomDossier::sectionText($section))
                        ->compact()
                        ->columns(['default' => 2, 'md' => 3])
                        ->schema(array_map(
                            fn (string $line, string $unit): TextInput => TextInput::make("quantities.{$section}.{$line}")
                                ->label(__("battery_rooms.sections.{$section}.lines.{$line}"))
                                ->numeric()
                                ->minValue(0)
                                ->step('any')
                                ->suffix(__("battery_rooms.units.{$unit}")),
                            array_keys(BatteryRoomDossier::SECTIONS[$section]),
                            BatteryRoomDossier::SECTIONS[$section],
                        )),
                    array_keys(BatteryRoomDossier::SECTIONS),
                )),
                Textarea::make('remarks')->label(__('battery_rooms.fields.remarks'))->rows(4)->maxLength(5000),
            ])->disabled(fn (?BatteryRoomDossier $record): bool => (bool) $record?->isLockedFor(auth()->user())),
            Select::make('document_locale')
                ->label(__('battery_rooms.fields.document_locale'))
                ->helperText(__('battery_rooms.fields.document_locale_help'))
                ->options(['nl' => 'Nederlands', 'fr' => 'Français'])
                ->placeholder(__('battery_rooms.automatic')),
            self::signatureSection(),
        ];
    }

    /**
     * The written signature on the delivery note, as on the 604 documents: drawing it saves the dossier
     * first and then locks the delivery note. Administrators can take it off again.
     */
    protected static function signatureSection(): Section
    {
        return Section::make(__('erp.batteries.signature.title'))
            ->icon(Heroicon::OutlinedPencil)
            ->compact()
            ->description(fn (?BatteryRoomDossier $record): string => $record?->isSigned()
                ? ($record->isLockedFor(auth()->user()) ? __('erp.batteries.signature.locked') : __('erp.batteries.signature.admin_unlocked'))
                : __('erp.batteries.signature.help'))
            ->schema([
                Text::make(fn (?BatteryRoomDossier $record): ?HtmlString => $record?->isSigned() ? new HtmlString(sprintf(
                    '<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;"><img src="%s" alt="" style="max-height:90px;background:#fff;border:1px solid rgb(203 213 225);border-radius:.375rem;padding:.25rem;"><div><strong>%s</strong><br><span style="opacity:.75;">%s</span></div></div>',
                    e((string) $record->signatureDataUri()),
                    e((string) $record->signer_name),
                    e(__('erp.batteries.signature.signed_at', ['date' => $record->signed_at->format('d/m/Y H:i'), 'user' => $record->signer?->name ?? '—'])),
                )) : null)->visible(fn (?BatteryRoomDossier $record): bool => (bool) $record?->isSigned()),
                Actions::make([
                    Action::make('sign')
                        ->label(__('erp.batteries.signature.sign'))
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('primary')
                        ->visible(fn (?BatteryRoomDossier $record): bool => $record !== null && ! $record->isSigned() && Gate::allows('update', $record))
                        ->modalHeading(__('erp.batteries.signature.sign_heading', ['document' => __('battery_rooms.tabs.note')]))
                        ->modalDescription(__('erp.batteries.signature.sign_help'))
                        ->schema([
                            TextInput::make('signer_name')
                                ->label(__('erp.batteries.signature.name'))
                                ->default(fn (): ?string => auth()->user()?->name)
                                ->required()
                                ->maxLength(150),
                            SignaturePad::make('signature')->label(__('erp.batteries.signature.title'))->required(),
                        ])
                        ->action(function (array $data, BatteryRoomDossier $record, EditBatteryRoom $livewire): void {
                            abort_unless(Gate::allows('update', $record), 403);

                            // Sign what is on screen: save the dossier first.
                            $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);
                            $record->sign($data['signer_name'], $data['signature'], auth()->user());
                            $livewire->refreshFormData([]);

                            Notification::make()->title(__('erp.batteries.signature.signed'))->success()->send();
                        }),
                    Action::make('unsign')
                        ->label(__('erp.batteries.signature.remove'))
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('erp.batteries.signature.remove_help'))
                        ->visible(fn (?BatteryRoomDossier $record): bool => (bool) $record?->isSigned() && (bool) auth()->user()?->isAdmin())
                        ->action(function (BatteryRoomDossier $record): void {
                            abort_unless((bool) auth()->user()?->isAdmin(), 403);
                            $record->unsign();

                            Notification::make()->title(__('erp.batteries.signature.removed'))->success()->send();
                        }),
                ])->key('signature_actions'),
            ]);
    }

    /**
     * @return array<Component>
     */
    protected static function checklistTab(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 3])->schema([
                DatePicker::make('checklist_date')->label(__('battery_rooms.fields.checklist_date'))->native(false)->displayFormat('d/m/Y'),
                Text::make(fn (?BatteryRoomDossier $record): string => __('battery_rooms.fields.cow_code').': '.($record?->cowAndRoom() ?: '—'))
                    ->weight(FontWeight::Bold)
                    ->columnSpan(2),
            ]),
            Text::make(__('battery_rooms.checklist_help'))->color('gray'),
            ...array_map(
                fn (string $point): Grid => Grid::make(['default' => 1, 'md' => 12])
                    ->schema([
                        Text::make(__("battery_rooms.checklist.{$point}.subject"))->weight(FontWeight::Bold)->columnSpan(['md' => 2]),
                        Text::make(__("battery_rooms.checklist.{$point}.description"))->color('gray')->columnSpan(['md' => 4]),
                        Checkbox::make("checklist.{$point}.ok")->label(__('battery_rooms.fields.ok'))->columnSpan(['md' => 1]),
                        Textarea::make("checklist.{$point}.action")
                            ->hiddenLabel()
                            ->placeholder(__('battery_rooms.fields.action'))
                            ->rows(2)
                            ->autosize()
                            ->maxLength(2000)
                            ->columnSpan(['md' => 5]),
                    ]),
                array_keys(BatteryRoomDossier::CHECKLIST),
            ),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function filesTab(): array
    {
        return [
            Text::make(__('battery_rooms.files_help'))->color('gray'),
            Section::make(__('battery_rooms.fields.photos'))->schema([
                PrivatePhotos::upload('photos', 'battery-rooms')
                    ->directory(fn (?BatteryRoomDossier $record): string => 'battery-rooms/'.$record?->id.'/photos')
                    ->maxFiles(BatteryRoomDossier::MAX_PHOTOS)
                    ->appendFiles()
                    ->reorderable()
                    ->openable()
                    ->hiddenLabel(),
            ]),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Section::make(__('battery_rooms.fields.ventilation'))->schema([
                    FileUpload::make('ventilation_file')
                        ->hiddenLabel()
                        ->disk(BatteryRoomDossier::DISK)
                        ->directory(fn (?BatteryRoomDossier $record): string => 'battery-rooms/'.$record?->id)
                        ->visibility('private')
                        ->acceptedFileTypes(self::DOCUMENT_TYPES)
                        ->maxSize(self::MAX_KILOBYTES)
                        ->storeFileNamesIn('ventilation_file_name')
                        ->downloadable(),
                ]),
                Section::make(__('battery_rooms.fields.btv'))->schema([
                    FileUpload::make('btv_file')
                        ->hiddenLabel()
                        ->disk(BatteryRoomDossier::DISK)
                        ->directory(fn (?BatteryRoomDossier $record): string => 'battery-rooms/'.$record?->id)
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(self::MAX_KILOBYTES)
                        ->storeFileNamesIn('btv_file_name')
                        // A stored PDF needs "battery_rooms.download_pdf" (see PdfAccess).
                        ->downloadable(fn (): bool => PdfAccess::canDownload(auth()->user(), 'battery_rooms')),
                ]),
            ]),
        ];
    }
}
