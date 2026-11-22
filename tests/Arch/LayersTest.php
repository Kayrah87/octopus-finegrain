<?php

declare(strict_types=1);

arch('domain is framework-free')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Application', 'App\Infrastructure', 'App\Http', 'Inertia']);

arch('application depends only on the domain')
    ->expect('App\Application')
    ->not->toUse(['Illuminate', 'App\Infrastructure', 'App\Http', 'Inertia']);

arch('infrastructure does not reach into presentation')
    ->expect('App\Infrastructure')
    ->not->toUse(['App\Http', 'App\Console', 'Inertia']);

arch('presentation does not touch persistence or Octopus adapters directly')
    ->expect(['App\Http', 'App\Console'])
    ->not->toUse(['App\Infrastructure', 'Illuminate\Support\Facades\DB']);

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('domain value objects are immutable')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Domain\Metering\MeterPointRepository', 'App\Domain\Metering\IntervalRepository']);

arch('no debugging calls')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
