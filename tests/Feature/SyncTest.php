<?php

declare(strict_types=1);

use App\Application\Ports\UsageReadModel;
use App\Application\Sync\RunFullSync;
use App\Jobs\SyncOctopusData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-01-06 09:00:00');
    fakeOctopus();
});

it('syncs, prices and aggregates an account end to end', function (): void {
    $summary = app(RunFullSync::class)->handle();

    expect($summary)->toContain('2 meter point(s)')
        ->and(DB::table('intervals')->count())->toBe(96)
        ->and(DB::table('intervals')->whereNull('cost_micropence')->count())->toBe(0);

    $electricityId = (int) DB::table('meter_points')->where('fuel', 'electricity')->value('id');
    $totals = app(UsageReadModel::class)->totals([$electricityId], '2026-01-05', '2026-01-05');

    // 48 × 0.5 kWh = 24 kWh at 24.5p = 588p, plus 50p standing charge.
    expect($totals->energy->toWattHours())->toBe(24_000)
        ->and($totals->unitCost->toMicropence())->toBe(588_000_000)
        ->and($totals->totalCost()->toPoundsString())->toBe('6.38');

    expect(DB::table('sync_runs')->value('succeeded'))->toBe(1);
});

it('converts gas volume to kWh before pricing', function (): void {
    app(RunFullSync::class)->handle();

    $gasId = (int) DB::table('meter_points')->where('fuel', 'gas')->value('id');
    $kwh = app(UsageReadModel::class)->totals([$gasId], '2026-01-05', '2026-01-05')->energy->kwh->toFloat();

    // 48 × 0.1 m³ × 1.02264 × 39.5 ÷ 3.6 ≈ 53.86 kWh
    expect(round($kwh, 1))->toBe(53.9);
});

it('is idempotent and resumes incrementally', function (): void {
    app(RunFullSync::class)->handle();
    app(RunFullSync::class)->handle();

    expect(DB::table('intervals')->count())->toBe(96)
        ->and(DB::table('daily_standing_charges')->count())->toBe(2);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'consumption')
        && str_contains(urldecode($request->url()), 'period_from=2026-01-03'));
});

it('only ever sends GET requests to the REST API', function (): void {
    app(RunFullSync::class)->handle();

    Http::assertNotSent(fn ($request): bool => $request->method() !== 'GET');
});

it('records a failed sync', function (): void {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['detail' => 'Invalid API key'], 401)]);

    expect(fn () => app(RunFullSync::class)->handle())->toThrow(Exception::class);
    expect(DB::table('sync_runs')->value('succeeded'))->toBe(0);
});

it('queues a sync from the dashboard button', function (): void {
    Queue::fake();

    $this->post('/sync')->assertRedirect();

    Queue::assertPushed(SyncOctopusData::class);
});

it('runs from the console command', function (): void {
    $this->artisan('octopus:sync')->assertSuccessful();
});

it('refuses to run the command without credentials', function (): void {
    config(['octopus.api_key' => null]);

    $this->artisan('octopus:sync')->assertFailed();
});
