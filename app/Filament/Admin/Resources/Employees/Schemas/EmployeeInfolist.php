<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Enums\DriverLicenseCategory;
use App\Models\Employee;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.employees.tabs.personal'))
                            ->columnSpan(['lg' => 2])
                            ->columns(3)
                            ->schema([
                                TextEntry::make('address')
                                    ->label(__('erp.fields.address'))
                                    ->state(fn (Employee $record): ?string => $record->addressLine())
                                    ->placeholder('—')
                                    ->columnSpan(2),
                                TextEntry::make('date_of_birth')
                                    ->label(__('erp.employees.date_of_birth'))
                                    ->state(fn (Employee $record): ?string => $record->date_of_birth ? $record->date_of_birth->format('d/m/Y').' ('.$record->date_of_birth->age.')' : null)
                                    ->helperText(fn (Employee $record): ?string => $record->place_of_birth)
                                    ->placeholder('—'),
                                TextEntry::make('private_email')
                                    ->label(__('erp.employees.private_email'))
                                    ->url(fn (Employee $record): ?string => $record->private_email ? "mailto:{$record->private_email}" : null)
                                    ->placeholder('—'),
                                TextEntry::make('private_phone')
                                    ->label(__('erp.employees.private_phone'))
                                    ->url(fn (Employee $record): ?string => $record->private_phone ? 'tel:'.preg_replace('/[^+\d]/', '', $record->private_phone) : null)
                                    ->placeholder('—'),
                                TextEntry::make('mother_tongue')
                                    ->label(__('erp.employees.mother_tongue'))
                                    ->formatStateUsing(fn (?string $state): ?string => $state ? __("erp.employees.languages.{$state}") : null)
                                    ->placeholder('—'),
                                TextEntry::make('national_number')
                                    ->label(__('erp.employees.national_number'))
                                    ->formatStateUsing(fn (?string $state): ?string => Employee::formatNationalNumber($state))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('bank_account')
                                    ->label(__('erp.employees.bank_account'))
                                    ->formatStateUsing(fn (?string $state): ?string => Employee::formatIban($state))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('education_level')
                                    ->label(__('erp.employees.education_level'))
                                    ->placeholder('—'),
                            ]),
                        Section::make(__('erp.employees.tabs.employment'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('erp.employees.status'))
                                    ->state(fn (Employee $record): string => $record->isEmployed() ? __('erp.employees.employed') : __('erp.employees.left_on_date', ['date' => $record->left_on->format('d/m/Y')]))
                                    ->helperText(fn (Employee $record): ?string => $record->leaving_reason)
                                    ->badge()
                                    ->color(fn (Employee $record): string => $record->isEmployed() ? 'success' : 'gray'),
                                TextEntry::make('employment_date')
                                    ->label(__('erp.employees.employment_date'))
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                TextEntry::make('employment_category')
                                    ->label(__('erp.employees.employment_category'))
                                    ->placeholder('—'),
                                TextEntry::make('contract_term')
                                    ->label(__('erp.employees.contract_term'))
                                    ->placeholder('—'),
                                TextEntry::make('interim')
                                    ->label(__('erp.employees.interim'))
                                    ->state(fn (Employee $record): ?string => $record->is_interim ? trim(__('erp.employees.interim').($record->interim_agency ? ' · '.$record->interim_agency : '')) : null)
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn (Employee $record): bool => $record->is_interim),
                                IconEntry::make('is_mentor')
                                    ->label(__('erp.employees.is_mentor'))
                                    ->boolean(),
                                TextEntry::make('user.email')
                                    ->label(__('erp.employees.login_email'))
                                    ->helperText(fn (Employee $record): ?string => match (true) {
                                        $record->user === null => null,
                                        ! $record->user->is_active => __('erp.employees.login_disabled'),
                                        $record->welcome_sent_at !== null => __('erp.employees.welcome_sent_on', ['date' => $record->welcome_sent_at->format('d/m/Y H:i')]),
                                        default => null,
                                    })
                                    ->placeholder(__('erp.employees.no_account')),
                                TextEntry::make('medical_due')
                                    ->label(__('erp.employees.medical_due'))
                                    ->state(fn (Employee $record): ?string => $record->nextMedicalDue()?->format('d/m/Y'))
                                    ->badge()
                                    ->color(fn (Employee $record): string => $record->medicalOverdue() ? 'danger' : 'success')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.employees.tabs.emergency'))
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                TextEntry::make('emergency_contacts')
                                    ->hiddenLabel()
                                    ->state(fn (Employee $record): array => array_map(
                                        fn (array $contact): string => implode(' · ', array_filter([$contact['name'], $contact['relation'], $contact['phone']])),
                                        $record->emergencyContacts(),
                                    ))
                                    ->listWithLineBreaks()
                                    ->placeholder('—'),
                            ]),
                        Section::make(__('erp.employees.tabs.clothing'))
                            ->columnSpan(['lg' => 1])
                            ->columns(4)
                            ->schema([
                                TextEntry::make('size_pants')->label(__('erp.employees.size_pants_short'))->placeholder('—'),
                                TextEntry::make('size_shirt')->label(__('erp.employees.size_shirt_short'))->placeholder('—'),
                                TextEntry::make('size_sweater')->label(__('erp.employees.size_sweater_short'))->placeholder('—'),
                                TextEntry::make('size_shoes')->label(__('erp.employees.size_shoes_short'))->placeholder('—'),
                            ]),
                    ]),
                Section::make(__('erp.employees.tabs.driver_license'))
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextEntry::make('driver_license_categories')
                            ->label(__('erp.employees.driver_license_categories'))
                            ->state(fn (Employee $record): array => array_map(fn (DriverLicenseCategory $category): string => $category->value, $record->driverLicenseCategories()))
                            ->badge()
                            ->placeholder(__('erp.employees.no_driver_license')),
                        TextEntry::make('driver_license_number')
                            ->label(__('erp.employees.driver_license_number'))
                            ->placeholder('—'),
                        TextEntry::make('driver_license_expires_on')
                            ->label(__('erp.employees.driver_license_expires_on'))
                            ->date('d/m/Y')
                            ->badge()
                            ->color(fn (Employee $record): string => $record->driverLicenseExpiresSoon() ? 'danger' : 'success')
                            ->placeholder('—'),
                        TextEntry::make('driver_license_files')
                            ->label(__('erp.employees.driver_license_files'))
                            ->state(fn (Employee $record): array => array_map(
                                fn (int $index, string $path): string => sprintf(
                                    '<a href="%s" target="_blank" rel="noopener" style="text-decoration: underline;">%s</a>',
                                    e(route('employees.document', ['employee' => $record->id, 'kind' => 'license', 'id' => 0, 'file' => $index])),
                                    e($record->driverLicenseFileName($path)),
                                ),
                                array_keys($files = array_values((array) $record->driver_license_files)),
                                $files,
                            ))
                            ->html()
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                    ]),
                Section::make(__('erp.fields.notes'))
                    ->columnSpanFull()
                    ->visible(fn (Employee $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel()->prose(),
                    ]),
            ]);
    }
}
