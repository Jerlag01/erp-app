<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Support\StockQrExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * The QR codes of the stock items as Excel for Labelife (label 99010), filtered by category, manufacturer
 * or location, with a preview of the first rows.
 */
class StockQrExportPage extends Page
{
    protected static ?string $slug = 'stock-qr-export';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.stock-qr-export';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && modules()->stock() && Gate::allows('viewAny', StockItem::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.stock.qr_export.title');
    }

    public function getTitle(): string
    {
        return __('erp.stock.qr_export.title');
    }

    public function getSubheading(): ?string
    {
        return __('erp.stock.qr_export.subheading');
    }

    public function mount(): void
    {
        $this->form->fill(['active' => true, 'locale' => app()->getLocale()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(3)
            ->components([
                Select::make('category')
                    ->label(__('erp.fields.category'))
                    ->options(fn (): array => StockCategory::query()->topLevel()->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                    ->live(),
                Select::make('subcategory')
                    ->label(__('erp.fields.subcategory'))
                    ->options(fn (): array => StockCategory::groupedOptions())
                    ->searchable()
                    ->live(),
                Select::make('manufacturer')
                    ->label(__('erp.resources.manufacturer.singular'))
                    ->options(fn (): array => Manufacturer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('location')
                    ->label(__('erp.stock.qr_export.location'))
                    ->helperText(__('erp.stock.qr_export.location_help'))
                    ->options(fn (): array => Location::query()->visibleTo(auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                    ->live(),
                Select::make('locale')
                    ->label(__('erp.stock.qr_export.language'))
                    ->options(['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'])
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Toggle::make('active')
                    ->label(__('erp.stock.qr_export.only_active'))
                    ->inline(false)
                    ->live(),
            ]);
    }

    /**
     * @return array{category: ?int, subcategory: ?int, manufacturer: ?int, location: ?int, active: bool}
     */
    protected function filters(): array
    {
        $location = filled($this->data['location'] ?? null) && Location::query()->visibleTo(auth()->user())->whereKey($this->data['location'])->exists()
            ? (int) $this->data['location'] : null;

        return [
            'category' => filled($this->data['category'] ?? null) ? (int) $this->data['category'] : null,
            'subcategory' => filled($this->data['subcategory'] ?? null) ? (int) $this->data['subcategory'] : null,
            'manufacturer' => filled($this->data['manufacturer'] ?? null) ? (int) $this->data['manufacturer'] : null,
            'location' => $location,
            'active' => (bool) ($this->data['active'] ?? true),
        ];
    }

    protected function locale(): string
    {
        return in_array($this->data['locale'] ?? null, ['nl', 'fr', 'en'], true) ? $this->data['locale'] : app()->getLocale();
    }

    public function count(): int
    {
        return app(StockQrExport::class)->query($this->filters())->count();
    }

    /**
     * The first rows as they will be in the Excel.
     *
     * @return array{columns: list<string>, rows: Collection<int, list<string>>}
     */
    public function preview(): array
    {
        $export = app(StockQrExport::class);
        $columns = $export->columns($this->locale());

        return [
            'columns' => array_keys($columns),
            'rows' => $export->query($this->filters())->limit(10)->get()
                ->map(fn (StockItem $item): array => array_map(fn (\Closure $value): string => $value($item), array_values($columns))),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('erp.stock.qr_export.download'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): ?BinaryFileResponse {
                    if ($this->count() === 0) {
                        Notification::make()->title(__('erp.stock.qr_export.empty'))->warning()->send();

                        return null;
                    }

                    return response()->download(app(StockQrExport::class)->xlsx($this->filters(), $this->locale()), StockQrExport::filename())->deleteFileAfterSend();
                }),
        ];
    }
}
