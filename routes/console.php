<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// On Laravel Cloud, enable the "Scheduler" toggle on the App cluster; locally run `php artisan schedule:work`.

Schedule::command('calendar:sync')->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping()->onOneServer();

// Lets the dashboard warn when the scheduler has stopped (see SystemHealthWarning).
Schedule::call(fn () => Cache::forever('scheduler:last_run', now()->toIso8601String()))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->onOneServer();

// With the database queue driver there is no separate worker: the scheduler drains
// the queue each minute (so reminder retries still work). With a Cloud managed queue
// (QUEUE_CONNECTION=cloud) Cloud runs the workers and this is skipped; with `sync`
// jobs already run inline.
if (config('queue.default') === 'database') {
    Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
