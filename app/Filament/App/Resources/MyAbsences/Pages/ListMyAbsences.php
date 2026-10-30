<?php

namespace App\Filament\App\Resources\MyAbsences\Pages;

use App\Actions\ReportSickness;
use App\Enums\AbsenceType;
use App\Filament\App\Resources\MyAbsences\MyAbsenceResource;
use App\Models\Absence;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListMyAbsences extends ListRecords
{
    protected static string $resource = MyAbsenceResource::class;

    /**
     * Reporting sickness: recorded straight away as a medical absence, with the certificate if there is one.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('report_sickness')
                ->label(__('erp.absences.report'))
                ->icon(Heroicon::OutlinedHeart)
                ->color('danger')
                ->modalHeading(__('erp.absences.report'))
                ->modalDescription(__('erp.absences.report_help'))
                ->schema([
                    DatePicker::make('start_date')->label(__('erp.fields.start_date'))->native(false)->displayFormat('d/m/Y')->default(today())->required()->live(),
                    DatePicker::make('end_date')->label(__('erp.fields.end_date'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('start_date')->default(today())->required()->live(),
                    Toggle::make('half_day')
                        ->label(__('erp.fields.half_day'))
                        ->visible(fn (Get $get): bool => filled($get('start_date')) && $get('start_date') === $get('end_date')),
                    Textarea::make('note')->label(__('erp.fields.notes'))->rows(2)->maxLength(1000),
                    FileUpload::make('document')
                        ->label(__('erp.absences.certificate'))
                        ->helperText(__('erp.absences.upload_help'))
                        ->disk(Absence::DISK)
                        ->directory(Absence::directoryFor((int) auth()->id()))
                        ->visibility('private')
                        ->acceptedFileTypes(Absence::DOCUMENT_TYPES)
                        ->maxSize(Absence::DOCUMENT_MAX_KILOBYTES)
                        ->storeFileNamesIn('document_name'),
                ])
                ->action(function (array $data): void {
                    $path = is_array($data['document'] ?? null) ? (reset($data['document']) ?: null) : ($data['document'] ?? null);
                    $name = $data['document_name'] ?? null;
                    app(ReportSickness::class)->handle(auth()->user(), $data, $path, is_array($name) ? (reset($name) ?: null) : $name);
                })
                ->successNotificationTitle(__('erp.absences.reported')),
        ];
    }

    /**
     * One list per kind of absence the employee has (sickness always).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $days = Absence::query()->where('user_id', auth()->id())->selectRaw('type, sum(days) as total')->groupBy('type')->pluck('total', 'type');
        $tabs = [];

        // Sickness always (to report it), the other kinds only when the employee has them.
        foreach (array_filter(AbsenceType::cases(), fn (AbsenceType $type): bool => $type === AbsenceType::Medical || isset($days[$type->value])) as $type) {
            $tabs[$type->value] = Tab::make($type->getLabel())
                ->icon($type->getIcon())
                ->badge(($total = (float) ($days[$type->value] ?? 0)) > 0 ? rtrim(rtrim(number_format($total, 1, ',', ''), '0'), ',').' '.__('erp.absences.days_short') : null)
                ->badgeColor($type->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->value));
        }

        return $tabs;
    }
}
