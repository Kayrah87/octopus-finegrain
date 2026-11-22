<?php

declare(strict_types=1);

use App\Domain\Metering\ConsumptionReading;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\GasVolumeConverter;
use App\Domain\Metering\LocalSlot;
use App\Domain\Metering\ReadingNormaliser;
use Carbon\CarbonImmutable;

it('converts gas volume to kWh', function (): void {
    $energy = (new GasVolumeConverter('39.5'))->toEnergy('1');

    // 1 × 1.02264 × 39.5 ÷ 3.6 = 11.2206
    expect(round($energy->kwh->toFloat(), 4))->toBe(11.2206);
});

it('assigns local dates and slots across a DST change', function (): void {
    // 30 March 2025: clocks go forward at 01:00 UTC.
    $before = LocalSlot::of(CarbonImmutable::parse('2025-03-30 00:30', 'UTC'), 'Europe/London');
    $after = LocalSlot::of(CarbonImmutable::parse('2025-03-30 01:00', 'UTC'), 'Europe/London');
    $summerEvening = LocalSlot::of(CarbonImmutable::parse('2025-06-30 23:30', 'UTC'), 'Europe/London');

    expect([$before->date, $before->slot])->toBe(['2025-03-30', 1])
        ->and([$after->date, $after->slot])->toBe(['2025-03-30', 4])
        ->and([$summerEvening->date, $summerEvening->slot])->toBe(['2025-07-01', 1]);
});

it('normalises gas in m³ but leaves electricity as kWh', function (): void {
    $normaliser = new ReadingNormaliser(new GasVolumeConverter('39.5'), true, 'Europe/London');
    $reading = new ConsumptionReading(
        CarbonImmutable::parse('2026-01-10 00:00', 'UTC'),
        CarbonImmutable::parse('2026-01-10 00:30', 'UTC'),
        '0.5',
    );

    expect($normaliser->normalise(Fuel::Electricity, $reading)->energy->toWattHours())->toBe(500)
        ->and($normaliser->normalise(Fuel::Gas, $reading)->energy->toWattHours())->toBe(5610);
});
