<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vouchers:expire')->dailyAt('00:10')->withoutOverlapping();

// Also refreshed lazily from admin requests, for hosting without a cron.
Schedule::command('package:sync')->hourly()->withoutOverlapping();

// Before the gateway's Search Console sync (03:00) reads it.
Schedule::command('ai:site-index')->dailyAt('02:30')->withoutOverlapping();

// Core updates (AI_BUILD_PROMPT.md, "Cập nhật phiên bản core"): ask the update service once an
// hour at this website's own minute, so the fleet does not hit the gateway at the same time.
Schedule::command('core:poll')->hourlyAt(\App\Support\CoreUpdate\CoreUpdateService::pollMinute())->withoutOverlapping(120);
// An update or rollback clicked in the admin runs here, never in the web request.
Schedule::command('core:run-requested')->everyMinute()->withoutOverlapping(120);
