<?php

declare(strict_types=1);

use App\Domain\Analysis\Change;
use App\Domain\Analysis\UsageTotals;
use App\Domain\Metering\Agreement;
use App\Domain\Metering\FlowDirection;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\Interval;
use App\Domain\Metering\LocalSlot;
use App\Domain\Metering\MeterPoint;
use App\Domain\Pricing\IntervalPricer;
use App\Domain\Pricing\StandingChargeCalculator;
use App\Domain\Pricing\UnitRateResolver;
use App\Domain\Shared\Energy;
use App\Domain\Shared\Money;
use App\Domain\Tariffs\Economy7Window;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

function utc(string $s): CarbonImmutable
{
    return CarbonImmutable::parse($s, 'UTC');
}

function interval(string $start, string $kwh): Interval
{
    return new Interval(utc($start), utc($start)->addMinutes(30), Energy::ofKwh($kwh), LocalSlot::of(utc($start), 'Europe/London'));
}

function meterPointOn(string ...$tariffs): MeterPoint
{
    $agreements = [];
    foreach (array_values($tariffs) as $i => $code) {
        $agreements[] = new Agreement(TariffCode::parse($code), utc('2026-01-0'.($i * 2 + 1)), $i === count($tariffs) - 1 ? null : utc('2026-01-0'.($i * 2 + 3)));
    }

    return new MeterPoint(1, Fuel::Electricity, FlowDirection::Import, '123', ['S1'], $agreements);
}

it('prices each interval at the rate in force for its tariff', function (): void {
    $meterPoint = meterPointOn('E-1R-FIXED-C', 'E-1R-AGILE-C');
    $schedules = fn (TariffCode $t, RateKind $k): RateSchedule => new RateSchedule([
        new Rate(Money::ofPence($t->productCode === 'FIXED' ? '25' : '10.5'), utc('2026-01-01'), null),
    ]);

    $priced = (new IntervalPricer(new UnitRateResolver(new Economy7Window('00:30', '07:30'))))->price(
        $meterPoint,
        [interval('2026-01-01 10:00', '2'), interval('2026-01-04 10:00', '2'), interval('2025-12-01 10:00', '1')],
        $schedules,
    );

    expect($priced)->toHaveCount(2)
        ->and((string) $priced[0]->cost?->pence)->toBe('50')
        ->and((string) $priced[1]->cost?->pence)->toBe('21.0');
});

it('uses night rates inside the Economy 7 window', function (): void {
    $meterPoint = meterPointOn('E-2R-E7-C');
    $schedules = fn (TariffCode $t, RateKind $k): RateSchedule => new RateSchedule([
        new Rate(Money::ofPence($k === RateKind::Night ? '8' : '30'), utc('2026-01-01'), null),
    ]);

    $priced = (new IntervalPricer(new UnitRateResolver(new Economy7Window('00:30', '07:30'))))
        ->price($meterPoint, [interval('2026-01-02 02:00', '1'), interval('2026-01-02 18:00', '1')], $schedules);

    expect((string) $priced[0]->unitRate?->pence)->toBe('8')
        ->and((string) $priced[1]->unitRate?->pence)->toBe('30');
});

it('charges one standing charge per local day', function (): void {
    $meterPoint = meterPointOn('E-1R-FIXED-C');
    $schedules = fn (): RateSchedule => new RateSchedule([new Rate(Money::ofPence('48.79'), utc('2026-01-01'), null)]);

    $charges = (new StandingChargeCalculator('Europe/London'))->forDates($meterPoint, ['2025-12-31', '2026-01-02'], $schedules);

    expect($charges)->toHaveCount(1)
        ->and($charges[0]->date)->toBe('2026-01-02')
        ->and($charges[0]->amount->toMicropence())->toBe(48_790_000);
});

it('round-trips money through micropence and reports averages', function (): void {
    $totals = new UsageTotals(Energy::ofKwh('10'), Money::ofPence('245.5'), Money::ofPence('50'));

    expect(Money::ofMicropence(Money::ofPence('12.345678')->toMicropence())->pence->isEqualTo('12.345678'))->toBeTrue()
        ->and($totals->totalCost()->toPoundsString())->toBe('2.96')
        ->and((string) $totals->averageUnitRate()?->pence)->toBe('24.5500');
});

it('computes percentage change and tolerates a zero baseline', function (): void {
    expect((string) Change::between(BigDecimal::of(200), BigDecimal::of(150))->percent)->toBe('-25.0')
        ->and(Change::between(BigDecimal::zero(), BigDecimal::of(5))->percent)->toBeNull();
});
