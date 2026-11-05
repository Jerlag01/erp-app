<?php

namespace App\Actions\It;

use App\Filament\Pages\DeliveryReports;
use App\Models\ItAsset;
use App\Models\ItDeliveryReport;
use App\Models\SignedDocument;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Support\PushNotifications;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The delivery report of an IT asset handed to an employee:
 * - issue(): made at the checkout, with the details filled in there; the employee gets a notification
 *   in the platform and on their phone to sign it;
 * - sign(): the employee signs (platform or app); the signed PDF goes to their signed documents and the
 *   person who handed the asset over is told;
 * - cancelFor(): an asset taken back before the report was signed drops its unsigned report.
 * Always printed in the employee's language (nl, fr or en).
 */
class ItDeliveryReports
{
    public function __construct(protected PushNotifications $push) {}

    /**
     * What the checkout form starts with: the asset and the employee as they are now, and what the last
     * report of this asset said about its number and SIM.
     *
     * @return array<string, mixed>
     */
    public static function defaults(ItAsset $asset, ?User $user): array
    {
        $asset->loadMissing('model.manufacturer');
        $previous = ItDeliveryReport::query()->where('it_asset_id', $asset->id)->latest('id')->first();

        return [
            'job_title' => $user?->job_title,
            'site' => $user?->teams()->orderBy('name')->pluck('name')->implode(', ') ?: null,
            'device' => trim(($asset->model?->manufacturer?->name ?? '').' '.($asset->model?->name ?? '')) ?: $asset->name,
            'serial' => $asset->serial,
            'imei' => $previous?->detail('imei'),
            'phone_number' => $previous?->detail('phone_number'),
            'sim' => $previous?->detail('sim'),
            'accessories' => $previous?->detail('accessories'),
            'condition' => 'new',
        ];
    }

    /**
     * @param  array<string, mixed>  $details  the keys of ItDeliveryReport::DETAILS
     */
    public function issue(ItAsset $asset, User $employee, array $details, ?User $issuer = null, ?string $companySignature = null): ItDeliveryReport
    {
        $report = DB::transaction(function () use ($asset, $employee, $details, $issuer, $companySignature): ItDeliveryReport {
            $report = ItDeliveryReport::query()->create([
                'it_asset_id' => $asset->id,
                'user_id' => $employee->id,
                'issued_by' => $issuer?->id,
                'handed_over_on' => today(),
                'details' => collect($details)->only(ItDeliveryReport::DETAILS)->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)->all(),
                'company_signer_name' => $issuer?->name,
            ]);

            if (filled($companySignature)) {
                $report->update(['company_signature_path' => ItDeliveryReport::storeSignature($companySignature, "it-delivery/{$report->id}/company")]);
            }

            return $report;
        });

        $this->notifyEmployee($report);

        return $report;
    }

    /**
     * A notification in the platform and on the phone, in the employee's language.
     */
    public function notifyEmployee(ItDeliveryReport $report): void
    {
        $employee = $report->user;

        if ($employee === null) {
            return;
        }

        $locale = $report->locale();
        $title = __('erp.it.delivery_report.notification_title', [], $locale);
        $body = __('erp.it.delivery_report.notification_body', ['device' => $report->deviceLabel()], $locale);

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-pencil-square')
            ->warning()
            ->actions([Action::make('sign')->label(__('erp.it.delivery_report.sign', [], $locale))->url(DeliveryReports::getUrl(panel: 'app'))->markAsRead()])
            ->sendToDatabase($employee);

        $this->push->toUser($employee, $title, $body, ['screen' => 'it_delivery', 'id' => $report->id]);
    }

    /**
     * The employee signs: the signed PDF is stored in their signed documents.
     */
    public function sign(ItDeliveryReport $report, string $name, string $signature): SignedDocument
    {
        if ($report->isSigned()) {
            throw new \DomainException('This delivery report is already signed.');
        }

        $report->sign($name, $signature);
        $report->refresh();
        $locale = $report->locale();
        $title = __('erp.it.delivery_report.document_title', ['device' => $report->deviceLabel()], $locale);
        $path = "signed-documents/{$report->user_id}/".Str::slug($title).'-'.$report->signed_at->format('Ymd-His').'.pdf';
        Storage::disk(SignedDocument::DISK)->put($path, $this->render($report));

        $document = SignedDocument::query()->create([
            'user_id' => $report->user_id,
            'kind' => SignedDocument::KIND_IT_DELIVERY,
            'title' => $title,
            'path' => $path,
            'source_type' => $report->getMorphClass(),
            'source_id' => $report->id,
            'signed_at' => $report->signed_at,
        ]);

        if ($report->issuer && $report->issuer->isNot($report->user)) {
            $issuerLocale = $report->issuer->preferredLocale() ?? config('app.locale');
            Notification::make()
                ->title(__('erp.it.delivery_report.signed_title', ['name' => $report->user?->name], $issuerLocale))
                ->body($report->deviceLabel())
                ->icon('heroicon-o-check-badge')
                ->success()
                ->sendToDatabase($report->issuer);
        }

        return $document;
    }

    /**
     * An asset taken back: its unsigned reports are no longer needed.
     */
    public function cancelFor(ItAsset $asset): void
    {
        ItDeliveryReport::query()->where('it_asset_id', $asset->id)->unsigned()->get()->each->delete();
    }

    /**
     * The PDF (A4 portrait) in the employee's language, with the signatures that are there.
     */
    public function render(ItDeliveryReport $report): string
    {
        $report->loadMissing(['user.employee', 'asset']);
        $locale = $report->locale();
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return Pdf::loadView('it.delivery-report-pdf', [
                'report' => $report,
                'company' => ToolboxMeeting::companyName(),
                'employeeSignature' => $report->signatureDataUri($report->signature_path),
                'companySignature' => $report->signatureDataUri($report->company_signature_path),
            ])->setPaper('a4', 'portrait')->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    public static function filename(ItDeliveryReport $report): string
    {
        return Str::slug(__('erp.it.delivery_report.document_title', ['device' => $report->deviceLabel()], $report->locale())).'.pdf';
    }
}
