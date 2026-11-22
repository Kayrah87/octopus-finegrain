<?php

declare(strict_types=1);

use App\Application\Sync\RunFullSync;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-01-06 09:00:00');
    fakeOctopus();
    app(RunFullSync::class)->handle();
});

it('renders the overview anchored on the latest day with data', function (): void {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Overview')
        ->has('meters', 2)
        ->where('meters.0.label', 'Electricity')
        ->where('meters.0.latestDate', '2026-01-05')
        ->where('meters.0.periods.day.current.kwh', 24)
        ->where('meters.0.periods.day.current.cost', 6.38)
        ->where('meters.0.tariff.unitRate', 24.5)
        ->where('liveEnabled', false));
});

it('explores a range with drill-down metadata and a heatmap', function (): void {
    $this->get('/explore?from=2026-01-01&to=2026-01-05')->assertInertia(fn (Assert $page) => $page
        ->component('Explore')
        ->where('selection.granularity', 'day')
        ->where('selection.finer', 'half_hour')
        ->has('series', 5)
        ->where('series.4.kwh', 24)
        ->where('series.0.kwh', null)
        ->has('heatmap', 48));
});

it('explores half-hours for a single day', function (): void {
    $this->get('/explore?from=2026-01-05&to=2026-01-05')->assertInertia(fn (Assert $page) => $page
        ->where('selection.granularity', 'half_hour')
        ->has('series', 48)
        ->where('series.0.bucket', '2026-01-05T00:00:00Z'));
});

it('validates explore input', function (): void {
    $this->get('/explore?granularity=fortnight')->assertSessionHasErrors('granularity');
});

it('compares two days, each broken into half-hours', function (): void {
    $this->get('/compare?granularity=day&periods[]=2026-01-04&periods[]=2026-01-05')->assertInertia(fn (Assert $page) => $page
        ->component('Compare')
        ->has('periods', 2)
        ->where('periods.0.totals.kwh', 0)
        ->where('periods.1.totals.kwh', 24)
        ->where('periods.1.energyChangePercent', null)
        ->where('periods.1.daysWithData', 1)
        ->where('periods.1.costPerDay', 6.38)
        ->has('periods.1.series', 48));
});

it('compares months by default', function (): void {
    $this->get('/compare')->assertInertia(fn (Assert $page) => $page
        ->where('selection.granularity', 'month')
        ->where('periods.0.label', 'December 2025')
        ->where('periods.1.label', 'January 2026')
        ->where('periods.1.days', 31)
        ->where('periods.1.daysWithData', 1));
});

it('shows tariff history and recent rates', function (): void {
    $this->get('/tariffs')->assertInertia(fn (Assert $page) => $page
        ->component('Tariffs')
        ->where('agreements.0.code', 'E-1R-VAR-22-11-01-C')
        ->where('agreements.0.current', true)
        ->where('rates.standard.0.pence', 24.5)
        ->where('rates.standing.0.pence', 50));
});

it('reports live data as disabled without a GraphQL key', function (): void {
    $this->getJson('/live')->assertOk()->assertJson(['enabled' => false, 'points' => []]);
});
