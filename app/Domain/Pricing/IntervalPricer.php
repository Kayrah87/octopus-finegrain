<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Metering\Interval;
use App\Domain\Metering\MeterPoint;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;

/**
 * Prices half-hourly intervals against the tariff in force at each interval's start.
 */
final readonly class IntervalPricer
{
    public function __construct(private UnitRateResolver $resolver) {}

    /**
     * @param  iterable<Interval>  $intervals
     * @param  callable(TariffCode, RateKind): RateSchedule  $schedules
     * @return list<Interval> Only the intervals that could be priced.
     */
    public function price(MeterPoint $meterPoint, iterable $intervals, callable $schedules): array
    {
        $priced = [];

        foreach ($intervals as $interval) {
            $agreement = $meterPoint->agreementAt($interval->start);

            if ($agreement === null) {
                continue;
            }

            $kind = $this->resolver->kindAt($agreement->tariff, $interval->start);
            $rate = $schedules($agreement->tariff, $kind)->rateAt($interval->start);

            if ($rate !== null) {
                $priced[] = $interval->priced($rate->valueIncVat);
            }
        }

        return $priced;
    }
}
