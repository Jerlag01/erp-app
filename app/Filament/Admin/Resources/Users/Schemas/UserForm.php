<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->components([
                Section::make(__('erp.sections.user'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('erp.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('initials')
                            ->label(__('erp.fields.initials'))
                            ->helperText(fn (?User $record): string => __('erp.help.initials', ['initials' => $record?->referenceInitials() ?? 'JL']))
                            ->maxLength(4)
                            ->regex('/^[A-Za-z]{1,4}$/')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                        TextInput::make('job_title')
                            ->label(__('erp.fields.job_title'))
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label(__('erp.fields.phone'))
                            ->tel()
                            ->maxLength(30),
                        Select::make('locale')
                            ->label(__('erp.fields.language'))
                            ->options(config('app.locales'))
                            ->default(config('app.locale'))
                            ->required()
                            ->selectablePlaceholder(false),
                        TextInput::make('password')
                            ->label(__('erp.fields.password'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->rule(Password::defaults())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('erp.help.password_keep') : null),
                    ]),
                Section::make(__('erp.sections.access'))
                    ->columns(2)
                    ->schema([
                        Select::make('role_id')
                            ->label(__('erp.resources.role.singular'))
                            ->relationship('role', 'name')
                            ->default(fn (): ?int => Role::guest()?->id)
                            ->preload()
                            ->required()
                            // Administrators cannot demote themselves and lock themselves out.
                            ->disabled($isSelf)
                            ->dehydrated(fn (?User $record): bool => ! $isSelf($record)),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.is_active'))
                            ->helperText(__('erp.help.is_active'))
                            ->default(true)
                            ->inline(false)
                            ->disabled($isSelf)
                            ->dehydrated(fn (?User $record): bool => ! $isSelf($record)),
                        Select::make('teams')
                            ->label(__('erp.resources.team.plural'))
                            ->helperText(fn (): ?string => ($guest = Role::guest()) ? __('erp.help.no_team', ['role' => $guest->name]) : null)
                            ->relationship('teams', 'name')
                            ->multiple()
                            ->preload()
                            ->visible(fn (): bool => modules()->teams())
                            ->columnSpanFull(),
                    ]),
                Section::make(__('erp.sections.vacation'))
                    ->visible(fn (): bool => modules()->vacations())
                    ->schema([
                        TextInput::make('vacation_days')
                            ->label(__('erp.fields.vacation_days'))
                            ->helperText(__('erp.help.vacation_days'))
                            ->numeric()
                            ->step(0.5)
                            ->minValue(0)
                            ->maxValue(60)
                            ->default(20)
                            ->required()
                            ->suffix(__('erp.vacations.days')),
                    ]),
            ]);
    }
}
