<?php

use Illuminate\Support\Facades\Schedule;

// Needs the server cron entry: * * * * * php8.4 /path/to/erp-app/artisan schedule:run
Schedule::command('stock:update-prices')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('finance:send-reminders')->dailyAt('08:00')->withoutOverlapping();
// The day after a notice period ends, the employee is no longer in service.
Schedule::command('employees:end-notice')->dailyAt('00:30')->withoutOverlapping();

// End of the week: the prestation sheets as PDF to the addresses of Settings > Prestations (day and time set there).
Schedule::command('timesheets:send-weekly')
    ->weeklyOn((int) settings()->get('timesheets.send_day', 5), (string) settings()->get('timesheets.send_time', '18:00'))
    ->withoutOverlapping();

// Planning notifications (push and e-mail) held while an employee was on leave: sent on the last day of the leave (see LeavePushDelay).
Schedule::command('planning:send-held')->everyFiveMinutes()->withoutOverlapping();

// The copy of the files on the NAS: what waits or failed (e.g. while the NAS was offline) goes out again.
Schedule::command('nas:sync')->everyTenMinutes()->withoutOverlapping();

// The orders placed at the distributors whose API offers them (Purchasing > Orders).
Schedule::command('distributors:sync-orders')->hourly()->withoutOverlapping();

// System backups to the backup targets that are due (Systeem → Back-ups); each target has its own time and frequency.
Schedule::command('backups:run')->everyFifteenMinutes()->withoutOverlapping();

// GPW requests follow their project as the days go by: "in progress" once a planned day of the period has come.
Schedule::command('gpw:sync-statuses')->dailyAt('00:15')->withoutOverlapping();

// Incoming invoices from Doccle and the invoice mailbox folder (each only when set up in Settings > Finance).
Schedule::command('finance:fetch-incoming')->everyFifteenMinutes()->withoutOverlapping();

// Bank movements through Ponto (Ponto itself refreshes about four times a day) and matching with invoices.
Schedule::command('banking:sync')->everyTwoHours()->withoutOverlapping();
