<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Mon 29 Dec 2025 – Fri 2 Jan 2026: one week spanning a month and year boundary.
 * Each day: a single 1 kWh half-hour at 10p, and a 50p standing charge.
 */
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-01-03 09:00:00');

    $id = DB::table('meter_points')->insertGetId(['fuel' => 'electricity', 'direction' => 'import', 'mpxn' => '123']);

    foreach (['2025-12-29', '2025-12-30', '2025-12-31', '2026-01-01', '2026-01-02'] as $date) {
        DB::table('intervals')->insert([
            'meter_point_id' => $id,
            'interval_start' => "{$date} 12:00:00",
            'interval_end' => "{$date} 12:30:00",
            'energy_wh' => 1000,
            'unit_rate_micropence' => 10_000_000,
            'cost_micropence' => 10_000_000,
            'local_date' => $date,
            'local_slot' => 24,
        ]);
        DB::table('daily_standing_charges')->insert(['meter_point_id' => $id, 'local_date' => $date, 'amount_micropence' => 50_000_000]);
    }
});

it('runs week to date across the year boundary while month and year restart', function (): void {
    $this->get('/explore?from=2025-12-29&to=2026-01-02&granularity=day')->assertInertia(fn (Assert $page) => $page
        // Wed 31 Dec: 3 days of week, month and year.
        ->where('series.2.cumulative.week', ['kwh' => 3, 'cost' => 1.8])
        ->where('series.2.cumulative.month', ['kwh' => 3, 'cost' => 1.8])
        ->where('series.2.cumulative.year', ['kwh' => 3, 'cost' => 1.8])
        // Fri 2 Jan: week still runs from Mon 29 Dec; month and year from 1 Jan.
        ->where('series.4.cumulative.week', ['kwh' => 5, 'cost' => 3])
        ->where('series.4.cumulative.month', ['kwh' => 2, 'cost' => 1.2])
        ->where('series.4.cumulative.year', ['kwh' => 2, 'cost' => 1.2]));
});

it('uses data outside the visible range for week to date', function (): void {
    $this->get('/explore?from=2026-01-02&to=2026-01-02')->assertInertia(fn (Assert $page) => $page
        ->where('selection.granularity', 'half_hour')
        // 12:00 slot on Fri 2 Jan: day so far = 1 kWh + 10p + 50p standing.
        ->where('series.0.cumulative.day', ['kwh' => 1, 'cost' => 0.6])
        ->where('series.0.cumulative.week', ['kwh' => 5, 'cost' => 3])
        ->where('series.0.cumulative.year', ['kwh' => 2, 'cost' => 1.2]));
});

it('gives month bars a year-to-date figure only', function (): void {
    $this->get('/explore?from=2025-12-01&to=2026-01-31&granularity=month')->assertInertia(fn (Assert $page) => $page
        ->where('series.0.cumulative', ['year' => ['kwh' => 3, 'cost' => 1.8]])
        ->where('series.1.cumulative', ['year' => ['kwh' => 2, 'cost' => 1.2]]));
});
