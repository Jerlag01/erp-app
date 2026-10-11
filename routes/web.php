<?php

use App\Http\Controllers\AbsenceDocumentController;
use App\Http\Controllers\BatteryDeliveriesPdfController;
use App\Http\Controllers\BatteryDocumentController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\FinanceDocumentController;
use App\Http\Controllers\IncidentPdfController;
use App\Http\Controllers\IncidentPhotoController;
use App\Http\Controllers\InterventionPdfController;
use App\Http\Controllers\ItAssetLabelController;
use App\Http\Controllers\ItAssetQrController;
use App\Http\Controllers\KbDownloadController;
use App\Http\Controllers\PeppolInboundController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\StockLabelController;
use App\Http\Controllers\StockQrController;
use App\Http\Controllers\SuggestionPdfController;
use App\Http\Controllers\SuggestionPhotoController;
use App\Http\Controllers\TimesheetPdfController;
use App\Http\Controllers\TimesheetReportPdfController;
use App\Http\Controllers\WastePdfController;
use App\Http\Controllers\WelcomeLinkController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// The employee panel (Filament) is served at the site root; see AppPanelProvider.

Route::get('/q/{item}', StockQrController::class)->name('stock.qr');
Route::get('/it/{asset}', ItAssetQrController::class)->name('it.qr');

Route::middleware('auth')->group(function (): void {
    Route::get('/stock/labels', StockLabelController::class)->name('stock.labels');
    Route::get('/it-labels', ItAssetLabelController::class)->name('it.labels');
    Route::get('/incidents/{incident}/photos/{index}', IncidentPhotoController::class)->whereNumber('index')->name('incidents.photo');
    Route::get('/incident-pdf', IncidentPdfController::class)->name('incidents.pdf');
    Route::get('/idea-box/{suggestion}/photos/{index}', SuggestionPhotoController::class)->whereNumber('index')->name('suggestions.photo');
    Route::get('/idea-box-pdf', SuggestionPdfController::class)->name('suggestions.pdf');
    Route::get('/projects/{project}/files/{file}/{version?}', ProjectFileController::class)->whereNumber(['project', 'file', 'version'])->name('projects.file');
    Route::get('/timesheet-report-pdf', TimesheetReportPdfController::class)->name('timesheets.report-pdf');
    Route::get('/timesheet-pdf/{user}/{week}', TimesheetPdfController::class)->whereNumber('user')->where('week', '\d{4}-\d{2}-\d{2}')->name('timesheets.pdf');
    Route::get('/battery-deliveries-pdf', BatteryDeliveriesPdfController::class)->name('batteries.deliveries-pdf');
    Route::get('/battery-dossiers/{dossier}/documents/{document}', BatteryDocumentController::class)->whereNumber('dossier')->where('document', '[a-z_]+')->name('batteries.document');
    Route::get('/interventions/{report}/pdf', InterventionPdfController::class)->whereNumber('report')->name('interventions.pdf');
    Route::get('/absences/{absence}/certificate', AbsenceDocumentController::class)->whereNumber('absence')->name('absences.document');
    Route::get('/finance/invoices/{invoice}/{format}', [FinanceDocumentController::class, 'invoice'])->whereIn('format', ['pdf', 'ubl'])->name('finance.invoice-document');
    Route::get('/finance/expenses/{expense}/{kind}', [FinanceDocumentController::class, 'expense'])->whereIn('kind', ['document', 'ubl', 'signature'])->name('finance.expense-document');
    Route::get('/waste-pdf/{token}', [WastePdfController::class, 'list'])->name('waste.pdf');
    Route::get('/waste-totals-pdf', [WastePdfController::class, 'totals'])->name('waste.totals-pdf');
    Route::get('/knowledge-base/{article}/files/{index}', KbDownloadController::class)->whereNumber(['article', 'index'])->name('kb.download');
    Route::get('/employee-documents/{employee}/{kind}/{id}', EmployeeDocumentController::class)->whereIn('kind', ['certificate', 'medical'])->whereNumber(['employee', 'id'])->name('employees.document');
});

// Received Peppol documents, POSTed by the access point (no session, so no CSRF token).
Route::post('/peppol/inbound/{secret}', PeppolInboundController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')
    ->name('peppol.inbound');

// The link in the welcome mail: signed, valid for 7 days, and only until the password is set.
Route::get('/welcome/{user}', WelcomeLinkController::class)->middleware('signed')->name('welcome');
