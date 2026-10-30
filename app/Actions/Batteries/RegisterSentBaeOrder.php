<?php

namespace App\Actions\Batteries;

use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\ProjectFile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What follows a sent BAE order:
 * - the 604 project moves to "Ordered - to be delivered" (unless the work has already started);
 * - the order PDF goes into the project's Offers tab (a new version when it is sent again);
 * - the project gets its line in the battery deliveries overview with the type, the number of cells,
 *   the cost and the order PDF. The order confirmation number is "0000000" and the delivery date the
 *   requested one of the order, both "to be confirmed" until the supplier's confirmation is filled in.
 */
class RegisterSentBaeOrder
{
    /** The order confirmation number until the supplier confirms the order. */
    public const UNCONFIRMED_NUMBER = '0000000';

    /** Statuses the project keeps: the work has started or is done. */
    public const KEEPS_STATUS = [ProjectStatus::InProgress, ProjectStatus::Executed, ProjectStatus::InvoicingToDo, ProjectStatus::Invoiced, ProjectStatus::Closed];

    public function __construct(protected BaeOrderDocument $document) {}

    public function handle(BaeOrder $order, User $by): BatteryDelivery
    {
        $order->loadMissing(['project.workSite', 'project.category', 'batteryModel']);
        $project = $order->project;
        $pdf = $this->document->render($order);
        $name = BaeOrderDocument::filename($order);

        return DB::transaction(function () use ($order, $project, $pdf, $name, $by): BatteryDelivery {
            if (! in_array($project->status, self::KEEPS_STATUS, true)) {
                $project->update(['status' => ProjectStatus::Ordered]);
            }

            // Offers tab: the same file name again is the next version of that file.
            $path = ProjectFile::directoryFor($project, ProjectFileSection::Offers).'/'.uniqid().'-'.$name;
            Storage::disk(ProjectFile::DISK)->put($path, $pdf);
            $existing = $project->offers()->where('name', $name)->first();
            ProjectFile::store($project, ProjectFileSection::Offers, $path, $name, $by, $existing);

            $delivery = BatteryDelivery::query()->firstOrNew(['project_id' => $project->id]);
            $requested = self::parseDate($order->delivery_date);

            $delivery->fill([
                'battery_model_id' => $order->battery_model_id ?? $delivery->battery_model_id,
                'pieces' => $order->totalCells() ?: $delivery->pieces,
                'total_cost' => $order->total(),
                // Only while not confirmed by the supplier yet.
                'order_confirmation' => blank($delivery->order_confirmation) || $delivery->order_confirmation === self::UNCONFIRMED_NUMBER ? self::UNCONFIRMED_NUMBER : $delivery->order_confirmation,
            ]);

            if (! $delivery->exists || ! $delivery->delivery_date_confirmed || $delivery->planned_delivery_date === null) {
                $delivery->planned_delivery_date = $requested;
                $delivery->delivery_date_confirmed = false;
            }

            // A requested date that is no date ("week 47"): kept in the notes.
            if ($requested === null && filled($order->delivery_date) && ! str_contains((string) $delivery->notes, (string) $order->delivery_date)) {
                $delivery->notes = trim($delivery->notes."\n".__('erp.batteries.requested_delivery_date', ['date' => $order->delivery_date]));
            }

            $delivery->save();

            $documentPath = 'battery-deliveries/'.$delivery->id.'/'.$name;
            Storage::disk(BatteryDelivery::DISK)->put($documentPath, $pdf);
            // The same path again: the model must not delete the file it has just been given.
            if ($delivery->order_document !== $documentPath) {
                $delivery->update(['order_document' => $documentPath, 'order_document_name' => $name]);
            }

            return $delivery;
        });
    }

    /**
     * The requested delivery date of the order (free text): "20/11/2026", "1-11-2026", "20.11.26" or
     * "2026-11-20"; anything else ("week 47") is no date.
     */
    public static function parseDate(?string $text): ?CarbonImmutable
    {
        $text = trim((string) $text);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $match)) {
            [$year, $month, $day] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        } elseif (preg_match('~^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})$~', $text, $match)) {
            [$day, $month, $year] = [(int) $match[1], (int) $match[2], (int) $match[3]];
            $year += $year < 100 ? 2000 : 0;
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day) : null;
    }
}
