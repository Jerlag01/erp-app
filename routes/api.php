<?php

use App\Http\Controllers\Api\V1\AssetCheckController;
use App\Http\Controllers\Api\V1\BatteryDossierController;
use App\Http\Controllers\Api\V1\BatteryRoomController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\PlanningController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\SuggestionController;
use App\Http\Controllers\Api\V1\TimesheetController;
use App\Http\Controllers\Api\V1\ToolboxController;
use App\Http\Middleware\AuthenticateMobileKey;
use Illuminate\Support\Facades\Route;

// The mobile app. Signing in issues a key and secret per device (valid for a year); every other call
// sends them as "Authorization: Bearer <key>.<secret>".

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

    Route::middleware([AuthenticateMobileKey::class, 'throttle:120,1'])->group(function (): void {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', ProfileController::class)->name('me');
        Route::put('/push-token', PushTokenController::class)->name('push-token');

        // The employee's own prestation sheet.
        Route::get('/timesheet/week', [TimesheetController::class, 'week'])->name('timesheet.week');
        Route::get('/timesheet/projects', [TimesheetController::class, 'projects'])->name('timesheet.projects');
        Route::get('/timesheet/days/{date}', [TimesheetController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('timesheet.day');
        Route::put('/timesheet/days/{date}', [TimesheetController::class, 'update'])->where('date', '\d{4}-\d{2}-\d{2}')->name('timesheet.save');

        // Vacation, overtime as paid leave and sickness.
        Route::get('/leave', [LeaveController::class, 'index'])->name('leave');
        Route::post('/leave/requests', [LeaveController::class, 'store'])->name('leave.request');
        Route::post('/leave/requests/{vacationRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
        Route::post('/leave/sickness', [LeaveController::class, 'reportSickness'])->name('leave.sickness');
        Route::post('/leave/absences/{absence}/document', [LeaveController::class, 'uploadDocument'])->name('leave.document');

        // The planning, read-only.
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning');
        Route::get('/planning/people', [PlanningController::class, 'people'])->name('planning.people');

        // The yearly equipment control.
        Route::get('/asset-checks', [AssetCheckController::class, 'index'])->name('asset-checks');
        Route::get('/asset-checks/{assetCheck}', [AssetCheckController::class, 'show'])->name('asset-checks.show');
        Route::put('/asset-checks/{assetCheck}/items/{item}', [AssetCheckController::class, 'updateItem'])->name('asset-checks.item');
        Route::put('/asset-checks/{assetCheck}/remarks', [AssetCheckController::class, 'remarks'])->name('asset-checks.remarks');
        Route::post('/asset-checks/{assetCheck}/proposals', [AssetCheckController::class, 'propose'])->name('asset-checks.propose');

        // Incidents and the idea/complaint box.
        Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents');
        Route::get('/incidents/work-sites', [IncidentController::class, 'workSites'])->name('incidents.work-sites');
        Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('/suggestions', [SuggestionController::class, 'index'])->name('suggestions');
        Route::post('/suggestions', [SuggestionController::class, 'store'])->name('suggestions.store');

        // Toolbox meetings: read and sign.
        Route::get('/toolbox', [ToolboxController::class, 'index'])->name('toolbox');
        Route::get('/toolbox/{meeting}', [ToolboxController::class, 'show'])->whereNumber('meeting')->name('toolbox.show');
        Route::post('/toolbox/{meeting}/read', [ToolboxController::class, 'read'])->whereNumber('meeting')->name('toolbox.read');
        Route::post('/toolbox/{meeting}/attachments/{index}', [ToolboxController::class, 'attachment'])->whereNumber(['meeting', 'index'])->name('toolbox.attachment');
        Route::post('/toolbox/{meeting}/sign', [ToolboxController::class, 'sign'])->whereNumber('meeting')->name('toolbox.sign');

        // Battery (604, "bae") and Enersys (605) dossiers.
        Route::prefix('/dossiers/{kind}')->whereIn('kind', array_keys(BatteryDossierController::KINDS))->name('dossiers.')->group(function (): void {
            Route::get('/', [BatteryDossierController::class, 'index'])->name('index');
            Route::get('/{id}', [BatteryDossierController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('/{id}', [BatteryDossierController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/signatures/{document}', [BatteryDossierController::class, 'sign'])->whereNumber('id')->name('sign');
            Route::post('/{id}/photos', [BatteryDossierController::class, 'addPhoto'])->whereNumber('id')->name('photos.add');
            Route::get('/{id}/photos/{index}', [BatteryDossierController::class, 'photo'])->whereNumber(['id', 'index'])->name('photos.show');
            Route::delete('/{id}/photos/{index}', [BatteryDossierController::class, 'removePhoto'])->whereNumber(['id', 'index'])->name('photos.remove');
            Route::post('/{id}/finish', [BatteryDossierController::class, 'finish'])->whereNumber('id')->name('finish');
            Route::put('/{id}/floor-plan', [BatteryDossierController::class, 'saveFloorPlan'])->whereNumber('id')->name('floor-plan.save');
            Route::get('/{id}/floor-plan', [BatteryDossierController::class, 'floorPlanImage'])->whereNumber('id')->name('floor-plan.show');
        });

        // Battery rooms (609).
        Route::prefix('/battery-rooms')->name('battery-rooms.')->group(function (): void {
            Route::get('/', [BatteryRoomController::class, 'index'])->name('index');
            Route::get('/{id}', [BatteryRoomController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('/{id}', [BatteryRoomController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/photos', [BatteryRoomController::class, 'addPhoto'])->whereNumber('id')->name('photos.add');
            Route::get('/{id}/photos/{index}', [BatteryRoomController::class, 'photo'])->whereNumber(['id', 'index'])->name('photos.show');
            Route::delete('/{id}/photos/{index}', [BatteryRoomController::class, 'removePhoto'])->whereNumber(['id', 'index'])->name('photos.remove');
            Route::post('/{id}/sign', [BatteryRoomController::class, 'sign'])->whereNumber('id')->name('sign');
        });
    });
});
