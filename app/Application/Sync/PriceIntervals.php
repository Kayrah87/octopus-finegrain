<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\MeterPoint;
use App\Domain\Pricing\IntervalPricer;
use App\Domain\Pricing\StandingChargeCalculator;
use App\Domain\Pricing\StandingChargeRepository;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use App\Domain\Tariffs\TariffRateRepository;

/**
 * Prices any unpriced intervals and records daily standing charges.
 */
final class PriceIntervals
{
    /** @var array<string, RateSchedule> */
    private array $schedules = [];

    public function __construct(
        private readonly IntervalRepository $intervals,
        private readonly TariffRateRepository $rates,
        private readonly StandingChargeRepository $standingCharges,
        private readonly IntervalPricer $pricer,
        private readonly StandingChargeCalculator $standingCalculator,
    ) {}

    /** @return int Number of intervals priced. */
    public function handle(MeterPoint $meterPoint): int
    {
        assert($meterPoint->id !== null);

        $schedules = $this->scheduleLookup(...);
        $priced = 0;

        foreach ($this->intervals->unpricedChunks($meterPoint->id) as $chunk) {
            $result = $this->pricer->price($meterPoint, $chunk, $schedules);
            $this->intervals->savePrices($meterPoint->id, $result);
            $priced += count($result);
        }

        $dates = $this->intervals->datesMissingStandingCharge($meterPoint->id);
        $this->standingCharges->save(
            $meterPoint->id,
            $this->standingCalculator->forDates($meterPoint, $dates, $schedules),
        );

        $this->schedules = [];

        return $priced;
    }

    private function scheduleLookup(TariffCode $tariff, RateKind $kind): RateSchedule
    {
        return $this->schedules["{$tariff}|{$kind->value}"] ??= $this->rates->schedule($tariff, $kind);
    }
}
