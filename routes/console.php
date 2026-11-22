<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

$minutes = (int) config('dashboard.sync_interval_minutes', 60);
$cron = $minutes < 60
    ? sprintf('*/%d * * * *', $minutes)
    : sprintf('0 */%d * * *', max(1, min(23, intdiv($minutes, 60))));

Schedule::command('octopus:sync')
    ->cron($cron)
    ->when(fn (): bool => filled(config('octopus.api_key')) && filled(config('octopus.account_number')))
    ->withoutOverlapping(60)
    ->runInBackground();
