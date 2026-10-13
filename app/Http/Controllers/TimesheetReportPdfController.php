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
    public function __invoke(Request $request, TimesheetReport $report): Response
    {
        $user = $request->user();
        abort_unless(modules()->timesheets() && ($user->isAdmin() || $user->hasPermission('timesheets.see_all')) && PdfAccess::canGenerate($user, 'timesheets'), 403);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'span' => ['nullable', 'in:1,2,year'],
        ]);

        $months = TimesheetReport::months(CarbonImmutable::createFromFormat('!Y-m', $validated['month']), $validated['span'] ?? '1');
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
