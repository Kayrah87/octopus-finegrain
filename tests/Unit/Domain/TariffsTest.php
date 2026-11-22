<?php

declare(strict_types=1);

use App\Domain\Metering\Fuel;
use App\Domain\Shared\Money;
use App\Domain\Tariffs\Economy7Window;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use Carbon\CarbonImmutable;

it('parses single and two-rate tariff codes', function (): void {
    $agile = TariffCode::parse('E-1R-AGILE-24-10-01-C');
    expect($agile->fuel)->toBe(Fuel::Electricity)
        ->and($agile->productCode)->toBe('AGILE-24-10-01')
        ->and($agile->region)->toBe('C')
        ->and($agile->unitRateKinds())->toBe([RateKind::Standard]);

    $e7 = TariffCode::parse('E-2R-VAR-22-11-01-A');
    expect($e7->isTwoRate())->toBeTrue()
        ->and($e7->unitRateKinds())->toBe([RateKind::Day, RateKind::Night]);

    expect(TariffCode::parse('G-1R-VAR-22-11-01-N')->fuel)->toBe(Fuel::Gas);
});

it('rejects malformed tariff codes', function (): void {
    TariffCode::parse('NOT-A-TARIFF');
})->throws(InvalidArgumentException::class);

it('finds the rate in force at an instant', function (): void {
    $t = fn (string $s): CarbonImmutable => CarbonImmutable::parse($s, 'UTC');
    $schedule = new RateSchedule([
        new Rate(Money::ofPence('20'), $t('2026-01-01 00:30'), $t('2026-01-01 01:00')),
        new Rate(Money::ofPence('10'), $t('2026-01-01 00:00'), $t('2026-01-01 00:30')),
        new Rate(Money::ofPence('30'), $t('2026-01-02 00:00'), null),
    ]);

    expect((string) $schedule->rateAt($t('2026-01-01 00:15'))?->valueIncVat->pence)->toBe('10')
        ->and((string) $schedule->rateAt($t('2026-01-01 00:30'))?->valueIncVat->pence)->toBe('20')
        ->and($schedule->rateAt($t('2026-01-01 12:00')))->toBeNull()
        ->and((string) $schedule->rateAt($t('2030-01-01'))?->valueIncVat->pence)->toBe('30')
        ->and($schedule->rateAt($t('2025-12-31')))->toBeNull();
});

it('detects the Economy 7 night window, including across midnight', function (): void {
    $window = new Economy7Window('23:30', '06:30');
    $at = fn (string $time): CarbonImmutable => CarbonImmutable::parse("2026-03-01 {$time}", 'UTC');

    expect($window->isNight($at('23:45')))->toBeTrue()
        ->and($window->isNight($at('03:00')))->toBeTrue()
        ->and($window->isNight($at('06:30')))->toBeFalse()
        ->and($window->isNight($at('12:00')))->toBeFalse();
});
