<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('calendar:sync')->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('invitations:close-unanswered')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::call(fn () => Cache::forever('scheduler:last_run', now()->toIso8601String()))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->onOneServer();

// No separate queue worker on Cloud: the scheduler processes the database queue every minute.
if (config('queue.default') === 'database') {
    Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
