<?php

use Illuminate\Support\Facades\Schedule;

// Needs the server cron entry: * * * * * php8.4 /path/to/erp-app/artisan schedule:run
Schedule::command('stock:update-prices')->dailyAt('05:30')->withoutOverlapping();
