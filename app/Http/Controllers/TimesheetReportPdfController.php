<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Pages\TimesheetMonthlyOverview;
use App\Support\PdfAccess;
use App\Support\TimesheetReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everyone's prestations of a month, two months or a year as PDF, for administrators and
 * "timesheets.see_all".
 */
class TimesheetReportPdfController extends Controller
{
    /**
     * Memory for a report of more than two months (see __invoke).
     */
    public const YEAR_MEMORY_LIMIT = '2048M';

    public function __invoke(Request $request, TimesheetReport $report): Response
    {
        $user = $request->user();
        abort_unless(modules()->timesheets() && ($user->isAdmin() || $user->hasPermission('timesheets.see_all')) && PdfAccess::canGenerate($user, 'timesheets'), 403);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'span' => ['nullable', 'in:1,2,year'],
        ]);

        $months = TimesheetReport::months(CarbonImmutable::createFromFormat('!Y-m', $validated['month']), $validated['span'] ?? '1');

        // dompdf keeps every table cell of the document in memory until the last page: a year of 25
        // employees is some 40.000 cells and needs about 600 MB, far above the usual 128 MB per request.
        if (count($months) > 2) {
            ini_set('memory_limit', self::YEAR_MEMORY_LIMIT);
            set_time_limit(300);
        }
        $title = TimesheetMonthlyOverview::title($months);

        $pdf = Pdf::loadView('timesheets.report-pdf', [
            'report' => $report->build($months),
            'title' => $title,
            'rate' => $report->mobilityRate(),
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($title).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
