<?php

namespace App\Actions\Batteries;

use App\Models\BaeOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The BAE order as PDF, laid out like the Excel sheet "Order" (calc.xlsx). It goes to the supplier,
 * so it is always in English, as in the Excel.
 */
class BaeOrderDocument
{
    public const VCA_SEAL = 'pdf/bae-order-vca.png';

    public function render(BaeOrder $order): string
    {
        return Pdf::loadHTML($this->html($order))
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function html(BaeOrder $order): string
    {
        $order->loadMissing(['project.workSite', 'project.category', 'batteryModel']);

        return view('batteries.pdf.bae-order', [
            'order' => $order,
            'logo' => $this->logo(),
            'seal' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path(self::VCA_SEAL))),
        ])->render();
    }

    public static function filename(BaeOrder $order): string
    {
        return Str::slug('BAE order '.$order->ourReference()).'.pdf';
    }

    /**
     * The company logo of Settings > Appearance as data URI, as on the invoices.
     */
    protected function logo(): ?string
    {
        $logo = settings()->get('appearance.logo');
        $disk = Storage::disk('public');

        return $logo && $disk->exists($logo) ? 'data:'.$disk->mimeType($logo).';base64,'.base64_encode((string) $disk->get($logo)) : null;
    }
}
