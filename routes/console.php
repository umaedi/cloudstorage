<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:send')->everyMinute();

Schedule::call(function () {
    file_put_contents(storage_path('logs/cron-test.log'), now()." CRON JALAN\n", FILE_APPEND);
})->everyMinute();