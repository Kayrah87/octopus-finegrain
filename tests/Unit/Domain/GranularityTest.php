<?php

declare(strict_types=1);

use App\Domain\Shared\Granularity;
use Carbon\CarbonImmutable;

it('picks a granularity from range length', function (int $days, Granularity $expected): void {
    expect(Granularity::forDays($days))->toBe($expected);
})->with([
    [1, Granularity::HalfHour],
    [30, Granularity::Day],
    [180, Granularity::Week],
    [800, Granularity::Month],
]);

it('finds Monday-start weeks and month starts', function (): void {
    $sunday = CarbonImmutable::parse('2026-11-22', 'Europe/London');

    expect(Granularity::Week->bucketStart($sunday)->toDateString())->toBe('2026-11-16')
        ->and(Granularity::Month->bucketStart($sunday)->toDateString())->toBe('2026-11-01')
        ->and(Granularity::Month->next(CarbonImmutable::parse('2026-01-01'))->toDateString())->toBe('2026-02-01');
});
