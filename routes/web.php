<?php

use App\Http\Controllers\AbsenceDocumentController;
use App\Http\Controllers\AsbuiltDownloadController;
use App\Http\Controllers\BatteryDeliveriesPdfController;
use App\Http\Controllers\BatteryDocumentController;
use App\Http\Controllers\ClientRouterVpnController;
use App\Http\Controllers\ClientWelcomeLinkController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EnersysDocumentController;
use App\Http\Controllers\FinanceDocumentController;
use App\Http\Controllers\GpwDecisionController;
use App\Http\Controllers\IncidentPdfController;
use App\Http\Controllers\IncidentPhotoController;
use App\Http\Controllers\InterventionPdfController;
use App\Http\Controllers\ItAssetLabelController;
use App\Http\Controllers\ItAssetQrController;
use App\Http\Controllers\KbDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MobileAppDismissController;
use App\Http\Controllers\MobileAppDownloadController;
use App\Http\Controllers\PeppolInboundController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProjectArchiveController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\RouterVpnController;
use App\Http\Controllers\StockLabelController;
use App\Http\Controllers\StockQrController;
use App\Http\Controllers\SuggestionPdfController;
use App\Http\Controllers\SuggestionPhotoController;
use App\Http\Controllers\SuggestionResponsePhotoController;
use App\Http\Controllers\TimesheetPdfController;
use App\Http\Controllers\TimesheetReportPdfController;
use App\Http\Controllers\ToolboxAttachmentController;
use App\Http\Controllers\ToolboxPdfController;
use App\Http\Controllers\WastePdfController;
use App\Http\Controllers\WelcomeLinkController;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// The employee panel (Filament) is served at the site root; see AppPanelProvider.

Route::get('/q/{item}', StockQrController::class)->name('stock.qr');
Route::get('/it/{asset}', ItAssetQrController::class)->name('it.qr');

// The VPN file of a router for the client portal (its own guard; checked in the controller).
Route::get('/client-files/asbuilts/{asbuilt}/zip', [AsbuiltDownloadController::class, 'clientZip'])->whereNumber('asbuilt')->name('client.asbuilt.zip');
Route::get('/client-files/asbuilt-files/{file}', [AsbuiltDownloadController::class, 'clientFile'])->whereNumber('file')->name('client.asbuilt.file');
Route::get('/client-files/routers/{router}/vpn', ClientRouterVpnController::class)->whereNumber('router')->name('client.routers.vpn');
// The link in a client portal user's welcome mail: signed, valid for 7 days, and only until the password is set.
Route::get('/client-welcome/{clientUser}', ClientWelcomeLinkController::class)->whereNumber('clientUser')->middleware('signed')->name('client.welcome');

// The privacy policy of the mobile app: public (Google Play and the app link to it).
Route::get('/privacy', PrivacyPolicyController::class)->name('privacy');

// An attachment of a toolbox meeting through a short-lived signed link from the mobile app.
Route::get('/toolbox-files/{meeting}/{index}', ToolboxAttachmentController::class)->whereNumber('index')->middleware('signed')->name('toolbox.attachment');

// Downloads and PDFs in the signed-in user's language.
Route::middleware(['auth', SetUserLocale::class])->group(function (): void {
    Route::post('/locale', LocaleController::class)->name('locale.update');
    Route::get('/projects/{project}/archive', ProjectArchiveController::class)->whereNumber('project')->name('projects.archive');
    Route::get('/stock/labels', StockLabelController::class)->name('stock.labels');
    Route::get('/it-labels', ItAssetLabelController::class)->name('it.labels');
    Route::get('/incidents/{incident}/photos/{index}', IncidentPhotoController::class)->whereNumber('index')->name('incidents.photo');
    Route::get('/incident-pdf', IncidentPdfController::class)->name('incidents.pdf');
    Route::get('/idea-box/{suggestion}/photos/{index}', SuggestionPhotoController::class)->whereNumber('index')->name('suggestions.photo');
    Route::get('/asbuilt-files/{file}', [AsbuiltDownloadController::class, 'file'])->whereNumber('file')->name('asbuilt.file');
    Route::get('/asbuilt-standard-files/{file}', [AsbuiltDownloadController::class, 'library'])->whereNumber('file')->name('asbuilt.library-file');
    Route::get('/asbuilts/{asbuilt}/zip', [AsbuiltDownloadController::class, 'zip'])->whereNumber('asbuilt')->name('asbuilt.zip');
    Route::get('/idea-box-pdf', SuggestionPdfController::class)->name('suggestions.pdf');
    Route::get('/idea-box-replies/{response}/photos/{index}', SuggestionResponsePhotoController::class)->whereNumber(['response', 'index'])->name('suggestions.response-photo');
    Route::get('/projects/{project}/files/{file}/{version?}', ProjectFileController::class)->whereNumber(['project', 'file', 'version'])->name('projects.file');
    Route::get('/routers/{router}/vpn', RouterVpnController::class)->whereNumber('router')->name('routers.vpn');
    Route::get('/timesheet-report-pdf', TimesheetReportPdfController::class)->name('timesheets.report-pdf');
    Route::get('/timesheet-pdf/{user}/{week}', TimesheetPdfController::class)->whereNumber('user')->where('week', '\d{4}-\d{2}-\d{2}')->name('timesheets.pdf');
    Route::get('/battery-deliveries-pdf', BatteryDeliveriesPdfController::class)->name('batteries.deliveries-pdf');
    Route::get('/battery-dossiers/{dossier}/documents/{document}', BatteryDocumentController::class)->whereNumber('dossier')->where('document', '[a-z_]+')->name('batteries.document');
    Route::get('/enersys-dossiers/{dossier}/documents/{document}', EnersysDocumentController::class)->whereNumber('dossier')->where('document', '[a-z_]+')->name('enersys.document');
    Route::get('/interventions/{report}/pdf', InterventionPdfController::class)->whereNumber('report')->name('interventions.pdf');
    Route::get('/absences/{absence}/certificate', AbsenceDocumentController::class)->whereNumber('absence')->name('absences.document');
    Route::get('/finance/invoices/{invoice}/{format}', [FinanceDocumentController::class, 'invoice'])->whereIn('format', ['pdf', 'ubl'])->name('finance.invoice-document');
    Route::get('/finance/expenses/{expense}/{kind}', [FinanceDocumentController::class, 'expense'])->whereIn('kind', ['document', 'ubl', 'signature'])->name('finance.expense-document');
    Route::get('/mobile-app/download', MobileAppDownloadController::class)->name('mobile-app.download');
    Route::post('/mobile-app/dismiss', MobileAppDismissController::class)->name('mobile-app.dismiss');
    Route::get('/toolbox-meetings/{meeting}/pdf', ToolboxPdfController::class)->name('toolbox.pdf');
    Route::get('/waste-pdf/{token}', [WastePdfController::class, 'list'])->name('waste.pdf');
    Route::get('/waste-totals-pdf', [WastePdfController::class, 'totals'])->name('waste.totals-pdf');
    Route::get('/waste-regional-pdf', [WastePdfController::class, 'regional'])->name('waste.regional-pdf');
    Route::get('/waste-lines/{line}/pdf', [WastePdfController::class, 'linePdf'])->whereNumber('line')->name('waste.line-pdf');
    Route::get('/knowledge-base/{article}/files/{index}', KbDownloadController::class)->whereNumber(['article', 'index'])->name('kb.download');
    Route::get('/employee-documents/{employee}/{kind}/{id}/{file?}', EmployeeDocumentController::class)->whereIn('kind', ['certificate', 'medical', 'request', 'license'])->whereNumber(['employee', 'id', 'file'])->name('employees.document');
});

// Received Peppol documents, POSTed by the access point (no session, so no CSRF token).
Route::post('/peppol/inbound/{secret}', PeppolInboundController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')
    ->name('peppol.inbound');

// The link in a GPW request mail: the contact person approves or refuses the request (signed, until the period ends).
Route::get('/gpw/{gpwRequest}', [GpwDecisionController::class, 'show'])->whereNumber('gpwRequest')->middleware('signed')->name('gpw.decide');
Route::post('/gpw/{gpwRequest}', [GpwDecisionController::class, 'decide'])->whereNumber('gpwRequest')->middleware(['signed', 'throttle:20,1']);

// The link in the welcome mail: signed, valid for 7 days, and only until the password is set.
Route::get('/welcome/{user}', WelcomeLinkController::class)->middleware('signed')->name('welcome');
