<?php

use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TimesheetController;
use App\Http\Middleware\AuthenticateMobileKey;
use Illuminate\Support\Facades\Route;

// The mobile app. Signing in issues a key and secret per device (valid for a year); every other call
// sends them as "Authorization: Bearer <key>.<secret>".

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

    Route::middleware([AuthenticateMobileKey::class, 'throttle:120,1'])->group(function (): void {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', ProfileController::class)->name('me');

        // The employee's own prestation sheet.
        Route::get('/timesheet/week', [TimesheetController::class, 'week'])->name('timesheet.week');
        Route::get('/timesheet/projects', [TimesheetController::class, 'projects'])->name('timesheet.projects');
        Route::get('/timesheet/days/{date}', [TimesheetController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('timesheet.day');
        Route::put('/timesheet/days/{date}', [TimesheetController::class, 'update'])->where('date', '\d{4}-\d{2}-\d{2}')->name('timesheet.save');
    });
});
