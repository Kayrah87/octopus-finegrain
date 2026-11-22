<?php

declare(strict_types=1);

return [
    // Login is enforced only when BOTH are set.
    'username' => env('DASHBOARD_USERNAME'),
    'password' => env('DASHBOARD_PASSWORD'),

    // Display/calendar time zone. Storage is always UTC.
    'timezone' => env('DASHBOARD_TIMEZONE', 'Europe/London'),

    'sync_interval_minutes' => max(5, (int) env('SYNC_INTERVAL_MINUTES', 60)),
];
