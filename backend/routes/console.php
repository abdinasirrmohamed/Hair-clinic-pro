<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::store('file')->put('backup-scheduler-last-seen', now()->toIso8601String(), now()->addDays(2));
})->everyMinute()->name('backup-scheduler-heartbeat');
// Check every five minutes so a computer that was asleep at 02:00 catches up when it resumes.
Schedule::command('clinic:backup --if-due')->everyFiveMinutes()->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/database-backup.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
