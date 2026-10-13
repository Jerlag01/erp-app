<?php

namespace App\Http\Controllers;

use App\Actions\Timesheets\TimesheetPdf;
use App\Models\User;
use App\Support\PdfAccess;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of one week of an employee's prestation sheet.
 */
class TimesheetPdfController extends Controller
{
    public function __invoke(Request $request, User $user, string $week, Timesheets $timesheets, TimesheetPdf $pdf): Response
    {
        abort_unless($timesheets->canView($request->user(), $user) && PdfAccess::canGenerate($request->user(), 'timesheets'), 403);

        $monday = Timesheets::monday(rescue(fn () => CarbonImmutable::parse($week), null, false) ?? abort(404));

        return response($pdf->render($user, $monday), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.TimesheetPdf::filename($user, $monday).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
