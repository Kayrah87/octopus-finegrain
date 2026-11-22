<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Domain\Metering\Interval;
use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\MeterPoint;
use App\Domain\Pricing\IntervalPricer;
use App\Domain\Pricing\StandingChargeCalculator;
use App\Domain\Pricing\StandingChargeRepository;
use App\Domain\Shared\DateRange;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use App\Domain\Tariffs\TariffRateRepository;

/**
 * Prices any unpriced intervals and records daily standing charges.
 *
 * Unit rates are loaded per chunk for just that chunk's time window: half-hourly
 * tariffs such as Agile publish ~17,500 rates a year, too many to hold at once.
 */
final readonly class PriceIntervals
{
    public function __construct(
        private IntervalRepository $intervals,
        private TariffRateRepository $rates,
        private StandingChargeRepository $standingCharges,
        private IntervalPricer $pricer,
        private StandingChargeCalculator $standingCalculator,
    ) {}

    /** @return int Number of intervals priced. */
    public function handle(MeterPoint $meterPoint): int
    {
        assert($meterPoint->id !== null);

        $priced = 0;

        foreach ($this->intervals->unpricedChunks($meterPoint->id) as $chunk) {
            $result = $this->pricer->price($meterPoint, $chunk, $this->windowedSchedules($chunk));
            $this->intervals->savePrices($meterPoint->id, $result);
            $priced += count($result);
        }

        $this->standingCharges->save(
            $meterPoint->id,
            $this->standingCalculator->forDates(
                $meterPoint,
                $this->intervals->datesMissingStandingCharge($meterPoint->id),
                $this->memoised(fn (TariffCode $tariff, RateKind $kind): RateSchedule => $this->rates->schedule($tariff, $kind)),
            ),
        );

        return $priced;
    }

    /**
     * @param  list<Interval>  $chunk
     * @return callable(TariffCode, RateKind): RateSchedule
     */
    private function windowedSchedules(array $chunk): callable
    {
        if ($chunk === []) {
            return static fn (): RateSchedule => new RateSchedule([]);
        }

        $from = $chunk[0]->start;
        $to = $chunk[0]->end;

        foreach ($chunk as $interval) {
            $from = $from->min($interval->start);
            $to = $to->max($interval->end);
        }

        $window = new DateRange($from, $to);

        return $this->memoised(fn (TariffCode $tariff, RateKind $kind): RateSchedule => $this->rates->scheduleBetween($tariff, $kind, $window));
    }

    /**
     * @param  callable(TariffCode, RateKind): RateSchedule  $load
     * @return callable(TariffCode, RateKind): RateSchedule
     */
    private function memoised(callable $load): callable
    {
        $cache = [];

        return static function (TariffCode $tariff, RateKind $kind) use (&$cache, $load): RateSchedule {
            return $cache["{$tariff}|{$kind->value}"] ??= $load($tariff, $kind);
        };
    }
}
