<?php

namespace App\Filament\Resources\Teams\Schemas;

use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('erp.sections.team'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Select::make('leader_id')
                            ->label(__('erp.fields.leader'))
                            ->relationship('leader', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload(),
                        Select::make('location_id')
                            ->label(__('erp.resources.location.singular'))
                            ->relationship('location', 'name', fn (Builder $query) => $query->where('is_active', true)->visibleTo(auth()->user()))
                            ->preload()
                            ->visible(fn (): bool => modules()->locations()),
                        ColorPicker::make('color')
                            ->label(__('erp.fields.color'))
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.is_active'))
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->label(__('erp.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('erp.sections.members'))
                    ->schema([
                        Select::make('members')
                            ->hiddenLabel()
                            ->relationship('members', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => filled($record->job_title) ? "{$record->name} · {$record->job_title}" : $record->name)
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
