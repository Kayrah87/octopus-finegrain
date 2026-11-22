<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Sync\PriceIntervals;
use App\Domain\Metering\Agreement;
use App\Domain\Metering\FlowDirection;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\Interval;
use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\LocalSlot;
use App\Domain\Metering\MeterPoint;
use App\Domain\Metering\MeterPointRepository;
use App\Domain\Shared\Energy;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Shared\Money;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffCode;
use App\Domain\Tariffs\TariffRateRepository;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Seeder;

/**
 * Synthetic data for working on the UI without an Octopus account:
 * ~13 months of electricity on an Agile-style tariff and gas on a flat tariff.
 * Run with: php artisan db:seed
 */
final class DemoDataSeeder extends Seeder
{
    private const DAYS = 400;

    public function __construct(
        private readonly MeterPointRepository $meterPoints,
        private readonly IntervalRepository $intervals,
        private readonly TariffRateRepository $rates,
        private readonly PriceIntervals $pricing,
        private readonly LocalCalendar $calendar,
    ) {}

    public function run(): void
    {
        mt_srand(42);
        $start = $this->calendar->today()->subDays(self::DAYS)->utc();
        $end = $this->calendar->today()->utc();

        $agile = TariffCode::parse('E-1R-AGILE-24-10-01-C');
        $gasTariff = TariffCode::parse('G-1R-VAR-22-11-01-C');

        $electricity = $this->meterPoints->save(new MeterPoint(null, Fuel::Electricity, FlowDirection::Import, '1900000000001', ['DEMO-E1'], [
            new Agreement($agile, $start, null),
        ]));
        $gas = $this->meterPoints->save(new MeterPoint(null, Fuel::Gas, FlowDirection::Import, '9000000001', ['DEMO-G1'], [
            new Agreement($gasTariff, $start, null),
        ]));

        $this->rates->upsert($agile, RateKind::Standard, $this->agileRates($start, $end->addDay()));
        $this->rates->upsert($agile, RateKind::StandingCharge, [new Rate(Money::ofPence('47.85'), $start, null)]);
        $this->rates->upsert($gasTariff, RateKind::Standard, [new Rate(Money::ofPence('6.29'), $start, null)]);
        $this->rates->upsert($gasTariff, RateKind::StandingCharge, [new Rate(Money::ofPence('31.43'), $start, null)]);

        $this->intervals->upsert((int) $electricity->id, $this->profile($start, $end, Fuel::Electricity));
        $this->intervals->upsert((int) $gas->id, $this->profile($start, $end, Fuel::Gas));

        $this->pricing->handle($electricity);
        $this->pricing->handle($gas);
    }

    /** @return Generator<int, Rate> */
    private function agileRates(CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        for ($t = $from; $t->lessThan($to); $t = $t->addMinutes(30)) {
            $hour = $t->setTimezone($this->calendar->timezone)->hour;
            $base = match (true) {
                $hour >= 16 && $hour < 19 => 32,
                $hour >= 0 && $hour < 6 => 9,
                default => 19,
            };
            $pence = max(-2, $base + mt_rand(-50, 50) / 10);

            yield new Rate(Money::ofPence((string) $pence), $t, $t->addMinutes(30));
        }
    }

    /** @return Generator<int, Interval> */
    private function profile(CarbonImmutable $from, CarbonImmutable $to, Fuel $fuel): Generator
    {
        for ($t = $from; $t->lessThan($to); $t = $t->addMinutes(30)) {
            $local = $t->setTimezone($this->calendar->timezone);
            $hour = $local->hour + $local->minute / 60;
            $winter = (cos(($local->dayOfYear - 15) / 365 * 2 * M_PI) + 1) / 2;

            $kwh = $fuel === Fuel::Electricity
                ? 0.12 + 0.35 * exp(-(($hour - 18.5) ** 2) / 3) + 0.15 * exp(-(($hour - 7.5) ** 2) / 1.5) + 0.1 * $winter
                : (0.1 + 1.8 * $winter) * (exp(-(($hour - 7) ** 2) / 2) + 0.8 * exp(-(($hour - 19) ** 2) / 4));

            $kwh *= 0.8 + mt_rand(0, 40) / 100;

            yield new Interval($t, $t->addMinutes(30), Energy::ofKwh(number_format($kwh, 3, '.', '')), LocalSlot::of($t, $this->calendar->timezone));
        }
    }
}
