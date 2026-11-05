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
