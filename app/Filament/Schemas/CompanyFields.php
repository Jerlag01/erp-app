<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Name, website, email, phone, address, logo and notes, shared by manufacturers and distributors.
 */
class CompanyFields
{
    /**
     * @param  list<Field>  $extra  extra fields for the general section
     * @return list<Section|Grid>
     */
    public static function make(string $logoDirectory, array $extra = []): array
    {
        return [
            Grid::make(['lg' => 3])
                ->columnSpanFull()
                ->schema([
                    Section::make(__('erp.sections.general'))
                        ->columnSpan(['lg' => 2])
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('erp.fields.name'))
                                ->required()
                                ->maxLength(100)
                                ->unique(ignoreRecord: true)
                                ->columnSpanFull(),
                            TextInput::make('website')
                                ->label(__('erp.fields.website'))
                                ->url()
                                ->rule('url:http,https')
                                ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                ->maxLength(255),
                            ...$extra,
                            TextInput::make('email')
                                ->label(__('erp.fields.email'))
                                ->email()
                                ->prefixIcon(Heroicon::OutlinedEnvelope)
                                ->maxLength(150),
                            TextInput::make('phone')
                                ->label(__('erp.fields.phone'))
                                ->tel()
                                ->prefixIcon(Heroicon::OutlinedPhone)
                                ->maxLength(30),
                            ...AddressFields::make(),
                        ]),
                    Section::make(__('erp.fields.logo'))
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            // No SVG uploads: an SVG on the public disk could carry script.
                            FileUpload::make('logo')
                                ->hiddenLabel()
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)
                                ->disk('public')
                                ->directory($logoDirectory)
                                ->visibility('public')
                                ->imagePreviewHeight('80'),
                            Textarea::make('notes')
                                ->label(__('erp.fields.notes'))
                                ->rows(4),
                        ]),
                ]),
        ];
    }
}
