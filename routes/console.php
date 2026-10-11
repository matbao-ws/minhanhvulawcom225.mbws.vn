<?php

use App\Support\CoreUpdate\CoreUpdateService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vouchers:expire')->dailyAt('00:10')->withoutOverlapping();

// Expired API tokens (SANCTUM_EXPIRATION) stop working on their own; this only clears the rows.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()->withoutOverlapping();

// Unpaid online orders hand back stock, voucher uses and promotion quota (config/orders.php).
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();

// Also refreshed lazily from admin requests, for hosting without a cron.
Schedule::command('package:sync')->hourly()->withoutOverlapping();

// Before the gateway's Search Console sync (03:00) reads it.
Schedule::command('ai:site-index')->dailyAt('02:30')->withoutOverlapping();

// Core updates (AI_BUILD_PROMPT.md, "Cập nhật phiên bản core"): ask the update service once an
// hour at this website's own minute, so the fleet does not hit the gateway at the same time.
Schedule::command('core:poll')->hourlyAt(CoreUpdateService::pollMinute())->withoutOverlapping(120);
// An update or rollback clicked in the admin runs here, never in the web request.
Schedule::command('core:run-requested')->everyMinute()->withoutOverlapping(120);

// Queued mail (password reset, order status, owner notifications) would otherwise wait in `jobs`
// forever on shared hosting, which has no queue worker: each minute, work the queue for up to
// 50 s, in the background so the other tasks of the minute are not held up.
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--tries=3', '--sleep=1'])
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground()
    ->when(fn () => config('queue.scheduler_worker') && config('queue.default') !== 'sync');
