<?php

namespace App\Filament\Resources\OrderReferences\Pages;

use App\Actions\GenerateOrderReference;
use App\Filament\Resources\OrderReferences\OrderReferenceResource;
use App\Filament\Resources\OrderReferences\Widgets\OrderReferenceCounter;
use App\Models\OrderReference;
use App\Models\OrderReferenceCounter as Counter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageOrderReferences extends ManageRecords
{
    protected static string $resource = OrderReferenceResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            OrderReferenceCounter::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setCounter')
                ->label(__('erp.orders.set_counter'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                ->modalDescription(__('erp.orders.set_counter_help'))
                ->fillForm(fn (): array => ['last_number' => Counter::lastNumber((int) date('Y'))])
                ->schema([
                    TextInput::make('last_number')
                        ->label(__('erp.orders.last_number', ['year' => date('Y')]))
                        ->numeric()
                        ->integer()
                        ->minValue(fn (): int => (int) OrderReference::query()->where('year', date('Y'))->max('number'))
                        ->maxValue(99999)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(GenerateOrderReference::class)->setCounter((int) date('Y'), (int) $data['last_number']);
                    $this->dispatch('order-reference-taken');
                })
                ->successNotificationTitle(__('erp.orders.counter_set')),
            Action::make('next')
                ->label(__('erp.orders.next'))
                ->icon(Heroicon::OutlinedPlus)
                ->size('lg')
                ->visible(fn (): bool => (bool) auth()->user()?->can('create', OrderReference::class))
                ->keyBindings(['mod+shift+n'])
                ->action(function (): void {
                    $reference = app(GenerateOrderReference::class)->handle(auth()->user());

                    Notification::make()
                        ->title($reference->reference)
                        ->body(__('erp.orders.taken_body'))
                        ->success()
                        ->persistent()
                        ->send();

                    $this->dispatch('order-reference-taken');
                    $this->resetTable();
                }),
        ];
    }
}
